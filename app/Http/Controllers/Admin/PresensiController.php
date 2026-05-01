<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\NationalHoliday;
use App\Models\Perusahaan;
use App\Models\Presensi;
use App\Models\PresensiVerification;
use App\Models\LogPresensi;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Presensi\WorkScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class PresensiController extends Controller
{
    public function index(Request $request)
    {
        $scopeQuery = $request->user()->applyEmployeeScope(
            Employee::query()->where('status_resign', 'AKTIF')
        );
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();

        $departemens = Departemen::with('perusahaan')
            ->whereIn('id', $departemenIds)
            ->orderBy('departemen')
            ->get();

        $divisis = Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get();
        $areas = Perusahaan::whereIn('kode_perusahaan', $areaCodes)->orderBy('kode_perusahaan')->get();

        return view('admin.presensi.index', compact(
            'departemens',
            'divisis',
            'areas'
        ));
    }

    public function faceReview(Request $request)
    {
        $this->authorizeFaceReviewAccess($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queue', Presensi::STATUS_ABSEN_PENDING_REVIEW, Presensi::STATUS_ABSEN_REJECTED, Presensi::STATUS_ABSEN_VERIFIED, 'all'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $filters['status'] ?? 'queue';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));

        $summaryQuery = $this->scopedFaceReviewQuery($request);
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', Presensi::STATUS_ABSEN_PENDING_REVIEW)->count(),
            'rejected' => (clone $summaryQuery)->where('status', Presensi::STATUS_ABSEN_REJECTED)->count(),
            'verified' => (clone $summaryQuery)->where('status', Presensi::STATUS_ABSEN_VERIFIED)->count(),
        ];

        $query = $this->scopedFaceReviewQuery($request)
            ->with([
                'presensi.employee.departemen',
                'presensi.employee.divisi',
                'reviewer',
            ])
            ->when($status === 'queue', function ($query) {
                $query->whereIn('status', [
                    Presensi::STATUS_ABSEN_PENDING_REVIEW,
                    Presensi::STATUS_ABSEN_REJECTED,
                ]);
            })
            ->when(in_array($status, [
                Presensi::STATUS_ABSEN_PENDING_REVIEW,
                Presensi::STATUS_ABSEN_REJECTED,
                Presensi::STATUS_ABSEN_VERIFIED,
            ], true), fn($query) => $query->where('status', $status))
            ->when($dateFrom, fn($query) => $query->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($query) => $query->whereDate('tanggal', '<=', $dateTo))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nik_karyawan', 'like', '%' . $search . '%')
                        ->orWhereHas('presensi.employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('nama_karyawan', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByRaw("CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END", [
                Presensi::STATUS_ABSEN_PENDING_REVIEW,
                Presensi::STATUS_ABSEN_REJECTED,
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');

        $verifications = $query
            ->paginate(15)
            ->withQueryString();

        $verifications->getCollection()->transform(function (PresensiVerification $verification) {
            $verification->gps_log = $this->latestGpsEvidence($verification);
            $verification->face_meta_summary = $this->faceMetaSummary($verification->face_verification_meta);
            $verification->selfie_available = $this->resolveSelfieAbsolutePath($verification) !== null;

            return $verification;
        });

        return view('admin.presensi.face-review', [
            'verifications' => $verifications,
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $search,
            ],
        ]);
    }

    public function faceReviewSelfie(Request $request, PresensiVerification $verification)
    {
        $this->authorizeFaceReviewAccess($request, $verification);

        $absolutePath = $this->resolveSelfieAbsolutePath($verification);

        abort_unless($absolutePath, 404, 'Selfie presensi tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'image/jpeg',
            'Content-Disposition' => 'inline; filename="presensi-selfie"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function decideFaceReview(Request $request, PresensiVerification $verification)
    {
        $this->authorizeFaceReviewAccess($request, $verification);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([
                PresensiVerification::REVIEW_APPROVED,
                PresensiVerification::REVIEW_REJECTED,
            ])],
            'review_note' => ['nullable', 'required_if:decision,' . PresensiVerification::REVIEW_REJECTED, 'string', 'max:2000'],
        ]);

        $status = $validated['decision'] === PresensiVerification::REVIEW_APPROVED
            ? Presensi::STATUS_ABSEN_VERIFIED
            : Presensi::STATUS_ABSEN_REJECTED;
        $verified = $status === Presensi::STATUS_ABSEN_VERIFIED;
        $reviewPayload = [
            'decision' => $validated['decision'],
            'status' => $status,
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_by_name' => $request->user()->name,
            'reviewed_at' => now()->toIso8601String(),
            'source' => 'hr-manual-review',
        ];

        DB::transaction(function () use ($verification, $status, $verified, $validated, $request, $reviewPayload) {
            $lockedVerification = PresensiVerification::whereKey($verification->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVerification->status = $status;
            $lockedVerification->face_verified = $verified;
            $lockedVerification->face_verified_at = $verified ? now() : null;
            $lockedVerification->face_verification_method = 'hr-manual-review';
            $lockedVerification->face_verification_meta = $this->mergeHrReviewMeta(
                $lockedVerification->face_verification_meta,
                $reviewPayload
            );
            $lockedVerification->review_decision = $validated['decision'];
            $lockedVerification->review_note = $validated['review_note'] ?? null;
            $lockedVerification->reviewed_by = $request->user()->id;
            $lockedVerification->reviewed_at = now();
            $lockedVerification->save();

            $presensi = Presensi::whereKey($lockedVerification->presensi_id)
                ->lockForUpdate()
                ->first();

            if ($presensi && $this->verificationMatchesCurrentPresensiEvidence($presensi, $lockedVerification)) {
                $presensi->status_absen = $status;
                $presensi->face_verified = $verified;
                $presensi->face_verified_at = $verified ? now() : null;
                $presensi->face_verification_method = 'hr-manual-review';
                $presensi->face_verification_meta = $this->mergeHrReviewMeta(
                    $presensi->face_verification_meta,
                    $reviewPayload
                );
                $presensi->save();
            }
        });

        toast()->success('Berhasil', 'Keputusan review presensi wajah berhasil disimpan.');

        return back();
    }

    private function authorizeFaceReviewAccess(Request $request, ?PresensiVerification $verification = null): void
    {
        abort_unless(
            $request->user() && $request->user()->hasRole(['Super Admin', 'HR']),
            403
        );

        if (!$verification) {
            return;
        }

        abort_unless(
            $this->scopedFaceReviewQuery($request)->whereKey($verification->id)->exists(),
            403
        );
    }

    private function scopedFaceReviewQuery(Request $request)
    {
        return PresensiVerification::query()
            ->whereHas('presensi.employee', function ($employeeQuery) use ($request) {
                $request->user()->applyEmployeeScope($employeeQuery);
            });
    }

    private function latestGpsEvidence(PresensiVerification $verification): ?LogPresensi
    {
        $submittedAt = $verification->submitted_at ?: $verification->created_at;
        $baseQuery = LogPresensi::query()
            ->where('nik_karyawan', $verification->nik_karyawan);

        if ($submittedAt) {
            $nearSubmitLog = (clone $baseQuery)
                ->whereBetween('created_at', [
                    $submittedAt->copy()->subMinutes(5),
                    $submittedAt->copy()->addMinute(),
                ])
                ->orderByDesc('created_at')
                ->first();

            if ($nearSubmitLog) {
                return $nearSubmitLog;
            }
        }

        if ($verification->tanggal) {
            return (clone $baseQuery)
                ->whereDate('tanggal', $verification->tanggal->toDateString())
                ->orderByDesc('created_at')
                ->first();
        }

        return $baseQuery->orderByDesc('created_at')->first();
    }

    private function faceMetaSummary(?string $meta): array
    {
        $decoded = json_decode((string) $meta, true);

        if (!is_array($decoded)) {
            return [];
        }

        $server = is_array($decoded['server_face_verification'] ?? null)
            ? $decoded['server_face_verification']
            : [];
        $validation = is_array($decoded['server_validation'] ?? null)
            ? $decoded['server_validation']
            : [];
        $provider = $server['provider'] ?? null;

        return [
            'message' => $server['message'] ?? null,
            'method' => $server['method'] ?? ($validation['version'] ?? null),
            'provider' => is_array($provider) ? ($provider['name'] ?? $provider['provider'] ?? null) : $provider,
            'passive_liveness' => $server['passive_liveness'] ?? null,
            'client_distance' => $decoded['distance'] ?? null,
            'detection_score' => $decoded['detection_score'] ?? null,
            'screen_spoof_score' => $decoded['screen_spoof_score'] ?? null,
            'hr_review' => $decoded['hr_review'] ?? null,
        ];
    }

    private function resolveSelfieAbsolutePath(PresensiVerification $verification): ?string
    {
        $path = (string) $verification->face_selfie_path;

        if ($path === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));
        $expectedDirectory = 'presensi-selfie/' . $verification->nik_karyawan . '/';

        if (
            Str::contains($normalizedPath, '..') ||
            !Str::startsWith($normalizedPath, $expectedDirectory)
        ) {
            return null;
        }

        $absolutePath = public_path($normalizedPath);

        return File::isFile($absolutePath) ? $absolutePath : null;
    }

    private function mergeHrReviewMeta(?string $currentMeta, array $payload): string
    {
        $meta = json_decode((string) $currentMeta, true);

        if (!is_array($meta)) {
            $meta = [];
        }

        $meta['hr_review'] = $payload;

        return json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    private function verificationMatchesCurrentPresensiEvidence(Presensi $presensi, PresensiVerification $verification): bool
    {
        if (filled($verification->presensi_challenge_id) && filled($presensi->presensi_challenge_id)) {
            return hash_equals((string) $presensi->presensi_challenge_id, (string) $verification->presensi_challenge_id);
        }

        if (filled($verification->face_selfie_hash) && filled($presensi->face_selfie_hash)) {
            return hash_equals((string) $presensi->face_selfie_hash, (string) $verification->face_selfie_hash);
        }

        return (string) $presensi->id === (string) $verification->presensi_id;
    }

    private function generateCutoff($month)
    {
        $start = Carbon::parse($month)->subMonth()->startOfMonth()->addDays(15);
        $end   = Carbon::parse($month)->startOfMonth()->addDays(14);

        return [$start, $end];
    }

    public function dataPresensi(Request $request)
    {
        if (!$request->departemen) {
            return response()->json([
                "data" => [],
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "tanggalHeaders" => []
            ]);
        }

        [$start, $end] = $this->generateCutoff($request->cutoff_month);

        $dates = collect(CarbonPeriod::create($start, $end)->toArray());
        $nationalHolidaysByDate = NationalHoliday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($holiday) => $holiday->holiday_date->toDateString());

        $tanggalHeaders = $dates
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $tanggalMeta = $dates
            ->mapWithKeys(function ($date) use ($nationalHolidaysByDate) {
                $dateString = $date->format('Y-m-d');
                $holiday = $nationalHolidaysByDate->get($dateString);

                return [
                    $dateString => [
                        'day' => $date->translatedFormat('D'),
                        'is_sunday' => $date->isSunday(),
                        'is_national_holiday' => filled($holiday),
                        'holiday_name' => $holiday->holiday_name ?? null,
                    ],
                ];
            })
            ->toArray();

        $baseQuery = $this->scopedActiveEmployeeQuery($request, [
            'nik',
            'nama_karyawan',
            'departemen_id',
            'divisi_id',
            'work_pattern_id',
            'work_pattern_start_date',
        ]);

        $length = $request->length ?? 10;
        $startPage = $request->start ?? 0;

        $employeePage = (clone $baseQuery)
            ->skip($startPage)
            ->take($length)
            ->with('workPattern')
            ->get();

        $niks = $employeePage->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $verificationRows = DB::table('presensi_verifications')
            ->select('presensi_id', 'attendance_type', 'status')
            ->whereIn('presensi_id', $presensiRows->pluck('id')->filter()->values())
            ->get()
            ->groupBy('presensi_id');

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');
            $verificationByType = $this->verificationStatusByType($verificationRows->get($p->id));

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'status' => $p->status_presensi ? Presensi::shortStatus($p->status_presensi) : null,
                'm' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_masuk, $tgl),
                'i' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_istirahat, $tgl),
                'k' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_kembali_istirahat, $tgl),
                'p' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_pulang, $tgl),
                'm_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'masuk', $p->jam_masuk ? ($p->status_absen ?? null) : null),
                'i_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'istirahat', $p->jam_istirahat ? ($p->status_absen ?? null) : null),
                'k_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'kembali', $p->jam_kembali_istirahat ? ($p->status_absen ?? null) : null),
                'p_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'pulang', $p->jam_pulang ? ($p->status_absen ?? null) : null),
                'verification' => $p->status_absen ?? null,
            ];
        }

        $actualPresensiMap = $presensiMap;

        $offMap = app(WorkScheduleService::class)->buildOffStatusMap($employeePage, $start, $end, $presensiMap);

        foreach ($offMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = $payload;
            }
        }

        $alphaMap = app(OvertimeOrderService::class)->buildAcceptedAlphaMap($niks, $start, $end, $actualPresensiMap);

        foreach ($alphaMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = $payload;
            }
        }

        return DataTables::of($baseQuery)

            ->addColumn('nik_karyawan', fn($row) => $row->nik)
            ->addColumn('nama_karyawan', fn($row) => $row->nama_karyawan)
            ->addColumn('tanggal_data', function ($row) use ($tanggalHeaders, $presensiMap) {

                $data = [];

                foreach ($tanggalHeaders as $tgl) {
                    $data[$tgl] = $presensiMap[$row->nik][$tgl] ?? null;
                }

                return $data;
            })

            ->with([
                'tanggalHeaders' => $tanggalHeaders,
                'tanggalMeta' => $tanggalMeta,
            ])

            ->make(true);
    }

    public function export(Request $request)
    {
        if (!$request->departemen) {
            return back()->with('error', 'Departemen wajib dipilih');
        }

        [$start, $end] = $this->generateCutoff($request->cutoff_month);

        $dates = collect(CarbonPeriod::create($start, $end)->toArray());

        $tanggalHeaders = $dates
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $employeeQuery = $this->scopedActiveEmployeeQuery($request, [
            'nik',
            'nama_karyawan',
            'departemen_id',
            'divisi_id',
            'work_pattern_id',
            'work_pattern_start_date',
        ]);

        $employees = $employeeQuery
            ->with('workPattern')
            ->get();

        $niks = $employees->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $verificationRows = DB::table('presensi_verifications')
            ->select('presensi_id', 'attendance_type', 'status')
            ->whereIn('presensi_id', $presensiRows->pluck('id')->filter()->values())
            ->get()
            ->groupBy('presensi_id');

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');
            $verificationByType = $this->verificationStatusByType($verificationRows->get($p->id));

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'status' => $p->status_presensi ? Presensi::shortStatus($p->status_presensi) : '',
                'm' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_masuk, $tgl),
                'i' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_istirahat, $tgl),
                'k' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_kembali_istirahat, $tgl),
                'p' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_pulang, $tgl),
                'm_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'masuk', $p->jam_masuk ? ($p->status_absen ?? null) : null),
                'i_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'istirahat', $p->jam_istirahat ? ($p->status_absen ?? null) : null),
                'k_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'kembali', $p->jam_kembali_istirahat ? ($p->status_absen ?? null) : null),
                'p_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'pulang', $p->jam_pulang ? ($p->status_absen ?? null) : null),
                'verification' => $p->status_absen ?? null,
            ];
        }

        $actualPresensiMap = $presensiMap;

        $offMap = app(WorkScheduleService::class)->buildOffStatusMap($employees, $start, $end, $presensiMap);

        foreach ($offMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = [
                    'status' => $payload['status'] ?? '',
                    'm' => $payload['m'] ?? '',
                    'i' => $payload['i'] ?? '',
                    'k' => $payload['k'] ?? '',
                    'p' => $payload['p'] ?? '',
                    'm_status' => null,
                    'i_status' => null,
                    'k_status' => null,
                    'p_status' => null,
                ];
            }
        }

        $alphaMap = app(OvertimeOrderService::class)->buildAcceptedAlphaMap($niks, $start, $end, $actualPresensiMap);

        foreach ($alphaMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = [
                    'status' => $payload['status'] ?? '',
                    'm' => $payload['m'] ?? '',
                    'i' => $payload['i'] ?? '',
                    'k' => $payload['k'] ?? '',
                    'p' => $payload['p'] ?? '',
                    'm_status' => null,
                    'i_status' => null,
                    'k_status' => null,
                    'p_status' => null,
                ];
            }
        }

        return response()->streamDownload(function () use ($employees, $tanggalHeaders, $presensiMap) {

            $handle = fopen('php://output', 'w');

            // HEADER
            $header = ['NIK', 'Nama'];

            foreach ($tanggalHeaders as $tgl) {
                $header[] = Carbon::parse($tgl)->format('d');
            }

            fputcsv($handle, $header);

            // ROW DATA
            foreach ($employees as $emp) {

                $row = [
                    $emp->nik,
                    $emp->nama_karyawan
                ];

                foreach ($tanggalHeaders as $tgl) {

                    if (isset($presensiMap[$emp->nik][$tgl])) {

                        $p = $presensiMap[$emp->nik][$tgl];

                        $row[] = $p['status']
                            ? $p['status']
                            : $this->formatAttendanceExportCell($p);
                    } else {
                        $row[] = '';
                    }
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'Presensi_' . now()->format('Ymd_His') . '.csv');
    }

    private function formatAttendanceClock(?string $value, string $attendanceDate): ?string
    {
        if (!$value) {
            return null;
        }

        $clock = Carbon::parse($value);
        $suffix = $clock->toDateString() > Carbon::parse($attendanceDate)->toDateString() ? ' +1' : '';

        return $clock->format('H:i') . $suffix;
    }

    private function scopedActiveEmployeeQuery(Request $request, array $columns)
    {
        $query = Employee::query()
            ->select($columns)
            ->where('status_resign', 'AKTIF')
            ->where('departemen_id', $request->departemen);

        $request->user()->applyEmployeeScope($query);

        if ($request->divisi) {
            $query->where('divisi_id', $request->divisi);
        }

        return $query;
    }

    private function verificationStatusByType($rows): array
    {
        if (!$rows) {
            return [];
        }

        return collect($rows)
            ->pluck('status', 'attendance_type')
            ->all();
    }

    private function verificationStatusForAction(array $verificationByType, string $type, ?string $fallback): ?string
    {
        return $verificationByType[$type] ?? $fallback;
    }

    private function formatAttendanceExportCell(array $presensi): string
    {
        $parts = [];
        $types = [
            ['label' => 'M', 'time' => 'm', 'status' => 'm_status'],
            ['label' => 'I', 'time' => 'i', 'status' => 'i_status'],
            ['label' => 'K', 'time' => 'k', 'status' => 'k_status'],
            ['label' => 'P', 'time' => 'p', 'status' => 'p_status'],
        ];

        foreach ($types as $type) {
            $time = $presensi[$type['time']] ?? null;

            if (!$time) {
                continue;
            }

            $status = $this->shortVerificationStatus($presensi[$type['status']] ?? null);
            $parts[] = trim($type['label'] . ' ' . $time . ($status ? ' (' . $status . ')' : ''));
        }

        return trim(implode(' ', $parts));
    }

    private function shortVerificationStatus(?string $status): ?string
    {
        switch ($status) {
            case Presensi::STATUS_ABSEN_VERIFIED:
                return 'SV';
            case Presensi::STATUS_ABSEN_PENDING_REVIEW:
                return 'RV';
            case Presensi::STATUS_ABSEN_REJECTED:
                return 'RJ';
            default:
                return null;
        }
    }
}
