<?php

namespace App\Services\Presensi;

use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceAnomalyService
{
    private const ATTENDANCE_COMPANY_CODES = ['VDNI', 'VDNIP'];
    private const MAX_DATE_RANGE_DAYS = 62;
    private const GPS_ACCURACY_WARNING_METERS = 40;
    private const GPS_SPEED_WARNING_MPS = 40;
    private const SECURITY_SCORE_WARNING = 80;

    private $attendanceColumns = [];
    private $logColumns = [];
    private $verificationColumns = [];

    public function anomalyTypes(): array
    {
        return [
            'incomplete_clock' => [
                'label' => 'Jam tidak lengkap',
                'severity' => 'warning',
            ],
            'invalid_sequence' => [
                'label' => 'Urutan jam tidak wajar',
                'severity' => 'danger',
            ],
            'face_pending' => [
                'label' => 'Review wajah pending',
                'severity' => 'warning',
            ],
            'face_rejected' => [
                'label' => 'Verifikasi wajah ditolak',
                'severity' => 'danger',
            ],
            'face_unverified' => [
                'label' => 'Belum diverifikasi wajah',
                'severity' => 'secondary',
            ],
            'suspicious_score' => [
                'label' => 'Skor keamanan rendah',
                'severity' => 'danger',
            ],
            'missing_gps' => [
                'label' => 'Log GPS tidak ditemukan',
                'severity' => 'warning',
            ],
            'gps_unstable' => [
                'label' => 'GPS tidak stabil',
                'severity' => 'warning',
            ],
        ];
    }

    public function normalizeFilters(array $input): array
    {
        [$defaultStart, $defaultEnd] = $this->defaultCutoffPeriod();

        $start = !empty($input['date_from'])
            ? Carbon::parse($input['date_from'])->startOfDay()
            : $defaultStart;
        $end = !empty($input['date_to'])
            ? Carbon::parse($input['date_to'])->startOfDay()
            : $defaultEnd;

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > self::MAX_DATE_RANGE_DAYS) {
            $end = $start->copy()->addDays(self::MAX_DATE_RANGE_DAYS);
        }

        $anomaly = $input['anomaly'] ?? 'all';
        if ($anomaly !== 'all' && !array_key_exists($anomaly, $this->anomalyTypes())) {
            $anomaly = 'all';
        }

        return [
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'period_label' => $start->format('d M Y') . ' - ' . $end->format('d M Y'),
            'area' => $this->normalizeArrayFilter($input['area'] ?? []),
            'departemen_id' => $input['departemen_id'] ?? null,
            'divisi_id' => $input['divisi_id'] ?? null,
            'anomaly' => $anomaly,
        ];
    }

    public function filterOptions(User $user): array
    {
        $scopeQuery = $user->applyEmployeeScope(
            Employee::query()
                ->where('status_resign', 'AKTIF')
                ->whereIn('area_kerja', self::ATTENDANCE_COMPANY_CODES)
        );

        $departemenIds = (clone $scopeQuery)
            ->select('departemen_id')
            ->distinct()
            ->pluck('departemen_id')
            ->filter()
            ->values();
        $divisiIds = (clone $scopeQuery)
            ->select('divisi_id')
            ->distinct()
            ->pluck('divisi_id')
            ->filter()
            ->values();
        $areaCodes = (clone $scopeQuery)
            ->select('area_kerja')
            ->distinct()
            ->pluck('area_kerja')
            ->filter()
            ->values();

        return [
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)
                ->whereIn('kode_perusahaan', self::ATTENDANCE_COMPANY_CODES)
                ->orderBy('kode_perusahaan')
                ->get(),
            'departemens' => Departemen::with('perusahaan')
                ->whereIn('id', $departemenIds)
                ->orderBy('departemen')
                ->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)
                ->orderBy('nama_divisi')
                ->get(),
        ];
    }

    public function summary(User $user, array $filters): array
    {
        $summary = [
            'total' => 0,
        ];

        $totalQuery = $this->baseQuery($user, $filters);
        $this->applyAnomalyFilter($totalQuery, $filters['anomaly'] ?? 'all');
        $summary['total'] = $this->countRows($totalQuery);

        foreach (array_keys($this->anomalyTypes()) as $type) {
            $query = $this->baseQuery($user, $filters);
            $this->applyAnomalyFilter($query, $type);
            $summary[$type] = $this->countRows($query);
        }

        return $summary;
    }

    public function dataTable(User $user, array $filters, array $dataTableInput): array
    {
        $baseQuery = $this->baseQuery($user, $filters);
        $this->applyAnomalyFilter($baseQuery, $filters['anomaly'] ?? 'all');

        $recordsTotal = $this->countRows(clone $baseQuery);
        $filteredQuery = $this->applySearch(clone $baseQuery, trim((string) data_get($dataTableInput, 'search.value', '')));
        $recordsFiltered = $this->countRows(clone $filteredQuery);

        $length = min(max((int) ($dataTableInput['length'] ?? 25), 1), 100);
        $start = max((int) ($dataTableInput['start'] ?? 0), 0);

        $rows = $filteredQuery
            ->select($this->selectColumns())
            ->orderByDesc('absensis.tanggal')
            ->orderByDesc('absensis.id')
            ->skip($start)
            ->take($length)
            ->get();

        $verificationContext = $this->verificationContext($rows);
        $gpsContext = $this->gpsContext($rows);

        return [
            'draw' => (int) ($dataTableInput['draw'] ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows
                ->map(function ($row) use ($verificationContext, $gpsContext) {
                    $key = $this->rowContextKey($row->nik_karyawan, $row->tanggal);
                    $verifications = $verificationContext->get((string) $row->presensi_id, collect());
                    $gps = $gpsContext->get($key);

                    return $this->formatRow($row, $verifications, $gps);
                })
                ->values(),
            'summary' => $this->summary($user, $filters),
        ];
    }

    private function baseQuery(User $user, array $filters)
    {
        $query = Employee::query()
            ->join('absensis', 'absensis.nik_karyawan', '=', 'employees.nik')
            ->leftJoin('departemens', 'departemens.id', '=', 'employees.departemen_id')
            ->leftJoin('divisis', 'divisis.id', '=', 'employees.divisi_id')
            ->where('employees.status_resign', 'AKTIF')
            ->whereIn('employees.area_kerja', self::ATTENDANCE_COMPANY_CODES)
            ->whereBetween('absensis.tanggal', [$filters['date_from'], $filters['date_to']]);

        $user->applyEmployeeScope($query, 'employees');

        if (!empty($filters['area'])) {
            $query->whereIn('employees.area_kerja', $filters['area']);
        }

        if (!empty($filters['departemen_id'])) {
            $query->where('employees.departemen_id', $filters['departemen_id']);
        }

        if (!empty($filters['divisi_id'])) {
            $query->where('employees.divisi_id', $filters['divisi_id']);
        }

        return $query;
    }

    private function applySearch($query, string $search)
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($query) use ($search) {
            $query->where('employees.nik', 'like', '%' . $search . '%')
                ->orWhere('employees.nama_karyawan', 'like', '%' . $search . '%')
                ->orWhere('departemens.departemen', 'like', '%' . $search . '%')
                ->orWhere('divisis.nama_divisi', 'like', '%' . $search . '%');
        });
    }

    private function applyAnomalyFilter($query, ?string $type): void
    {
        if ($type && $type !== 'all' && array_key_exists($type, $this->anomalyTypes())) {
            $query->where(function ($query) use ($type) {
                $this->whereAnomaly($query, $type);
            });

            return;
        }

        $query->where(function ($query) {
            foreach (array_keys($this->anomalyTypes()) as $type) {
                $query->orWhere(function ($query) use ($type) {
                    $this->whereAnomaly($query, $type);
                });
            }
        });
    }

    private function whereAnomaly($query, string $type): void
    {
        switch ($type) {
            case 'incomplete_clock':
                $this->whereNoStatusPresensi($query);
                $query->where(function ($query) {
                    $query->whereNull('absensis.jam_masuk')
                        ->orWhereNull('absensis.jam_pulang')
                        ->orWhere(function ($query) {
                            $query->whereNull('absensis.jam_istirahat')
                                ->whereNotNull('absensis.jam_kembali_istirahat');
                        })
                        ->orWhere(function ($query) {
                            $query->whereNotNull('absensis.jam_istirahat')
                                ->whereNull('absensis.jam_kembali_istirahat');
                        });
                });
                break;

            case 'invalid_sequence':
                $this->whereNoStatusPresensi($query);
                $query->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereNotNull('absensis.jam_masuk')
                            ->whereNotNull('absensis.jam_istirahat')
                            ->whereColumn('absensis.jam_istirahat', '<', 'absensis.jam_masuk');
                    })->orWhere(function ($query) {
                        $query->whereNotNull('absensis.jam_istirahat')
                            ->whereNotNull('absensis.jam_kembali_istirahat')
                            ->whereColumn('absensis.jam_kembali_istirahat', '<', 'absensis.jam_istirahat');
                    })->orWhere(function ($query) {
                        $query->whereNotNull('absensis.jam_masuk')
                            ->whereNotNull('absensis.jam_pulang')
                            ->whereColumn('absensis.jam_pulang', '<', 'absensis.jam_masuk');
                    })->orWhere(function ($query) {
                        $query->whereNotNull('absensis.jam_kembali_istirahat')
                            ->whereNotNull('absensis.jam_pulang')
                            ->whereColumn('absensis.jam_pulang', '<', 'absensis.jam_kembali_istirahat');
                    });
                });
                break;

            case 'face_pending':
                $this->whereFaceStatus($query, Presensi::STATUS_ABSEN_PENDING_REVIEW);
                break;

            case 'face_rejected':
                $this->whereFaceStatus($query, Presensi::STATUS_ABSEN_REJECTED);
                break;

            case 'face_unverified':
                $this->whereNoStatusPresensi($query);
                $this->whereHasClock($query);

                if ($this->hasAttendanceColumn('status_absen')) {
                    $query->where(function ($query) {
                        $query->whereNull('absensis.status_absen')
                            ->orWhere('absensis.status_absen', '');
                    });
                }

                if ($this->hasAttendanceColumn('face_verified')) {
                    $query->where(function ($query) {
                        $query->whereNull('absensis.face_verified')
                            ->orWhere('absensis.face_verified', false);
                    });
                }

                if ($this->hasVerificationTable()) {
                    $query->whereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('presensi_verifications')
                            ->whereColumn('presensi_verifications.presensi_id', 'absensis.id');
                    });
                }
                break;

            case 'suspicious_score':
                if (!$this->hasAttendanceColumn('is_suspicious') && !$this->hasAttendanceColumn('security_score')) {
                    $query->whereRaw('1 = 0');
                    break;
                }

                $query->where(function ($query) {
                    if ($this->hasAttendanceColumn('is_suspicious')) {
                        $query->orWhere('absensis.is_suspicious', true);
                    }

                    if ($this->hasAttendanceColumn('security_score')) {
                        $query->orWhere(function ($query) {
                            $query->whereNotNull('absensis.security_score')
                                ->where('absensis.security_score', '<', self::SECURITY_SCORE_WARNING);
                        });
                    }
                });
                break;

            case 'missing_gps':
                if (!$this->hasLogTable() || !$this->hasLogColumn('tanggal')) {
                    $query->whereRaw('1 = 0');
                    break;
                }

                $this->whereHasClock($query);
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('log_presensi')
                        ->whereColumn('log_presensi.nik_karyawan', 'absensis.nik_karyawan')
                        ->whereColumn('log_presensi.tanggal', 'absensis.tanggal');
                });
                break;

            case 'gps_unstable':
                if (
                    !$this->hasLogTable()
                    || !$this->hasLogColumn('tanggal')
                    || (!$this->hasLogColumn('accuracy') && !$this->hasLogColumn('speed'))
                ) {
                    $query->whereRaw('1 = 0');
                    break;
                }

                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('log_presensi')
                        ->whereColumn('log_presensi.nik_karyawan', 'absensis.nik_karyawan')
                        ->whereColumn('log_presensi.tanggal', 'absensis.tanggal')
                        ->where(function ($query) {
                            if ($this->hasLogColumn('accuracy')) {
                                $query->orWhere('log_presensi.accuracy', '>', self::GPS_ACCURACY_WARNING_METERS);
                            }

                            if ($this->hasLogColumn('speed')) {
                                $query->orWhere('log_presensi.speed', '>', self::GPS_SPEED_WARNING_MPS);
                            }
                        });
                });
                break;
        }
    }

    private function whereFaceStatus($query, string $status): void
    {
        $query->where(function ($query) use ($status) {
            $hasCondition = false;

            if ($this->hasAttendanceColumn('status_absen')) {
                $query->where('absensis.status_absen', $status);
                $hasCondition = true;
            }

            if ($this->hasVerificationTable()) {
                $method = $hasCondition ? 'orWhereExists' : 'whereExists';
                $query->{$method}(function ($subQuery) use ($status) {
                    $subQuery->select(DB::raw(1))
                        ->from('presensi_verifications')
                        ->whereColumn('presensi_verifications.presensi_id', 'absensis.id')
                        ->where('presensi_verifications.status', $status);
                });
                $hasCondition = true;
            }

            if (!$hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function whereNoStatusPresensi($query): void
    {
        $query->where(function ($query) {
            $query->whereNull('absensis.status_presensi')
                ->orWhere('absensis.status_presensi', '');
        });
    }

    private function whereHasClock($query): void
    {
        $query->where(function ($query) {
            $query->whereNotNull('absensis.jam_masuk')
                ->orWhereNotNull('absensis.jam_istirahat')
                ->orWhereNotNull('absensis.jam_kembali_istirahat')
                ->orWhereNotNull('absensis.jam_pulang');
        });
    }

    private function countRows($query): int
    {
        return (int) $query->distinct('absensis.id')->count('absensis.id');
    }

    private function selectColumns(): array
    {
        return [
            'absensis.id as presensi_id',
            'absensis.nik_karyawan',
            'absensis.tanggal',
            'absensis.jam_masuk',
            'absensis.jam_istirahat',
            'absensis.jam_kembali_istirahat',
            'absensis.jam_pulang',
            'absensis.status_presensi',
            'employees.nama_karyawan',
            'employees.area_kerja',
            'departemens.departemen as departemen_name',
            'divisis.nama_divisi as divisi_name',
            $this->attendanceColumnExpression('status_absen'),
            $this->attendanceColumnExpression('face_verified'),
            $this->attendanceColumnExpression('face_selfie_path'),
            $this->attendanceColumnExpression('face_verification_distance'),
            $this->attendanceColumnExpression('security_score'),
            $this->attendanceColumnExpression('is_suspicious'),
            $this->attendanceColumnExpression('device_info'),
            $this->attendanceColumnExpression('ip_address'),
        ];
    }

    private function attendanceColumnExpression(string $column)
    {
        return $this->hasAttendanceColumn($column)
            ? 'absensis.' . $column . ' as ' . $column
            : DB::raw('NULL as ' . $column);
    }

    private function verificationContext(Collection $rows): Collection
    {
        if (!$this->hasVerificationTable()) {
            return collect();
        }

        $presensiIds = $rows->pluck('presensi_id')->filter()->values();

        if ($presensiIds->isEmpty()) {
            return collect();
        }

        return DB::table('presensi_verifications')
            ->select([
                'presensi_id',
                'attendance_type',
                'status',
                $this->verificationColumnExpression('face_verification_distance'),
                $this->verificationColumnExpression('submitted_at'),
            ])
            ->whereIn('presensi_id', $presensiIds)
            ->get()
            ->groupBy(fn($row) => (string) $row->presensi_id);
    }

    private function gpsContext(Collection $rows): Collection
    {
        if (!$this->hasLogTable() || !$this->hasLogColumn('tanggal')) {
            return collect();
        }

        $niks = $rows->pluck('nik_karyawan')->filter()->unique()->values();
        $dates = $rows->pluck('tanggal')->filter()->map(fn($date) => Carbon::parse($date)->toDateString());

        if ($niks->isEmpty() || $dates->isEmpty()) {
            return collect();
        }

        $query = DB::table('log_presensi')
            ->select([
                'nik_karyawan',
                'tanggal',
                $this->logColumnExpression('lat'),
                $this->logColumnExpression('long'),
                $this->logColumnExpression('accuracy'),
                $this->logColumnExpression('speed'),
                $this->logColumnExpression('created_at'),
            ])
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$dates->min(), $dates->max()]);

        if ($this->hasLogColumn('created_at')) {
            $query->orderByDesc('created_at');
        }

        $context = collect();

        foreach ($query->get() as $gps) {
            $key = $this->rowContextKey($gps->nik_karyawan, $gps->tanggal);

            if (!$context->has($key)) {
                $context->put($key, $gps);
            }
        }

        return $context;
    }

    private function verificationColumnExpression(string $column)
    {
        return $this->hasVerificationColumn($column)
            ? 'presensi_verifications.' . $column
            : DB::raw('NULL as ' . $column);
    }

    private function logColumnExpression(string $column)
    {
        return $this->hasLogColumn($column)
            ? 'log_presensi.' . $column
            : DB::raw('NULL as ' . $column);
    }

    private function formatRow($row, Collection $verifications, $gps): array
    {
        $anomalies = $this->rowAnomalies($row, $verifications, $gps);
        $tanggal = Carbon::parse($row->tanggal)->toDateString();

        return [
            'presensi_id' => $row->presensi_id,
            'tanggal' => $tanggal,
            'tanggal_label' => Carbon::parse($tanggal)->format('d M Y'),
            'nik_karyawan' => $row->nik_karyawan,
            'nama_karyawan' => $row->nama_karyawan,
            'area_kerja' => $row->area_kerja,
            'departemen' => $row->departemen_name ?: '-',
            'divisi' => $row->divisi_name ?: '-',
            'jam' => [
                'masuk' => $this->formatClock($row->jam_masuk),
                'istirahat' => $this->formatClock($row->jam_istirahat),
                'kembali' => $this->formatClock($row->jam_kembali_istirahat),
                'pulang' => $this->formatClock($row->jam_pulang),
            ],
            'status_presensi' => $row->status_presensi ?: '-',
            'status_absen' => $row->status_absen,
            'status_absen_label' => Presensi::statusAbsenLabel($row->status_absen),
            'security_score' => is_numeric($row->security_score) ? (int) $row->security_score : null,
            'gps' => $this->formatGps($gps),
            'device_info' => $row->device_info ?: '-',
            'ip_address' => $row->ip_address ?: '-',
            'anomalies' => $anomalies,
            'anomaly_labels' => collect($anomalies)->pluck('label')->implode(', '),
            'review_url' => $this->needsFaceReview($anomalies)
                ? route('data-presensi.face-review.index', [
                    'status' => 'queue',
                    'date_from' => $tanggal,
                    'date_to' => $tanggal,
                    'q' => $row->nik_karyawan,
                ])
                : null,
        ];
    }

    private function rowAnomalies($row, Collection $verifications, $gps): array
    {
        $types = $this->anomalyTypes();
        $result = [];
        $hasClock = $this->rowHasClock($row);
        $hasStatusPresensi = filled($row->status_presensi);
        $verificationStatuses = $verifications->pluck('status')->filter()->values()->all();

        if (!$hasStatusPresensi && (
            blank($row->jam_masuk)
            || blank($row->jam_pulang)
            || (blank($row->jam_istirahat) && filled($row->jam_kembali_istirahat))
            || (filled($row->jam_istirahat) && blank($row->jam_kembali_istirahat))
        )) {
            $result[] = $this->formatAnomaly('incomplete_clock', $types);
        }

        if (!$hasStatusPresensi && $this->hasInvalidSequence($row)) {
            $result[] = $this->formatAnomaly('invalid_sequence', $types);
        }

        if ($row->status_absen === Presensi::STATUS_ABSEN_PENDING_REVIEW || in_array(Presensi::STATUS_ABSEN_PENDING_REVIEW, $verificationStatuses, true)) {
            $result[] = $this->formatAnomaly('face_pending', $types);
        }

        if ($row->status_absen === Presensi::STATUS_ABSEN_REJECTED || in_array(Presensi::STATUS_ABSEN_REJECTED, $verificationStatuses, true)) {
            $result[] = $this->formatAnomaly('face_rejected', $types);
        }

        if (
            !$hasStatusPresensi
            && $hasClock
            && blank($row->status_absen)
            && ((int) $row->face_verified !== 1)
            && $verifications->isEmpty()
        ) {
            $result[] = $this->formatAnomaly('face_unverified', $types);
        }

        if (
            ((int) $row->is_suspicious === 1)
            || (is_numeric($row->security_score) && (int) $row->security_score < self::SECURITY_SCORE_WARNING)
        ) {
            $result[] = $this->formatAnomaly('suspicious_score', $types);
        }

        if ($hasClock && !$gps) {
            $result[] = $this->formatAnomaly('missing_gps', $types);
        }

        if ($gps && (
            (is_numeric($gps->accuracy) && (float) $gps->accuracy > self::GPS_ACCURACY_WARNING_METERS)
            || (is_numeric($gps->speed) && (float) $gps->speed > self::GPS_SPEED_WARNING_MPS)
        )) {
            $result[] = $this->formatAnomaly('gps_unstable', $types);
        }

        return $result;
    }

    private function formatAnomaly(string $key, array $types): array
    {
        return [
            'key' => $key,
            'label' => $types[$key]['label'],
            'severity' => $types[$key]['severity'],
        ];
    }

    private function hasInvalidSequence($row): bool
    {
        return $this->isBefore($row->jam_istirahat, $row->jam_masuk)
            || $this->isBefore($row->jam_kembali_istirahat, $row->jam_istirahat)
            || $this->isBefore($row->jam_pulang, $row->jam_masuk)
            || $this->isBefore($row->jam_pulang, $row->jam_kembali_istirahat);
    }

    private function isBefore($value, $reference): bool
    {
        if (!$value || !$reference) {
            return false;
        }

        return Carbon::parse($value)->lt(Carbon::parse($reference));
    }

    private function rowHasClock($row): bool
    {
        return filled($row->jam_masuk)
            || filled($row->jam_istirahat)
            || filled($row->jam_kembali_istirahat)
            || filled($row->jam_pulang);
    }

    private function formatClock($value): string
    {
        return $value ? Carbon::parse($value)->format('H:i') : '-';
    }

    private function formatGps($gps): array
    {
        if (!$gps) {
            return [
                'summary' => '-',
                'map_url' => null,
            ];
        }

        $parts = [];

        if (is_numeric($gps->accuracy)) {
            $parts[] = 'Akurasi ' . round((float) $gps->accuracy) . 'm';
        }

        if (is_numeric($gps->speed)) {
            $parts[] = 'Speed ' . round((float) $gps->speed, 1) . 'm/s';
        }

        if ($gps->created_at) {
            $parts[] = Carbon::parse($gps->created_at)->format('H:i:s');
        }

        return [
            'summary' => $parts ? implode(' / ', $parts) : 'Ada log GPS',
            'lat' => $gps->lat,
            'long' => $gps->long,
            'map_url' => (filled($gps->lat) && filled($gps->long))
                ? 'https://www.google.com/maps?q=' . $gps->lat . ',' . $gps->long
                : null,
        ];
    }

    private function needsFaceReview(array $anomalies): bool
    {
        $keys = collect($anomalies)->pluck('key');

        return $keys->contains('face_pending') || $keys->contains('face_rejected');
    }

    private function rowContextKey($nik, $date): string
    {
        return (string) $nik . '|' . Carbon::parse($date)->toDateString();
    }

    private function defaultCutoffPeriod(): array
    {
        $today = Carbon::today();

        if ($today->day >= 16) {
            $start = Carbon::create($today->year, $today->month, 16);
            $end = $start->copy()->addMonthNoOverflow()->day(15);
        } else {
            $start = Carbon::create($today->year, $today->month, 16)->subMonthNoOverflow();
            $end = Carbon::create($today->year, $today->month, 15);
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private function normalizeArrayFilter($value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn($item) => filled($item))
            ->values()
            ->all();
    }

    private function hasAttendanceColumn(string $column): bool
    {
        if (!array_key_exists($column, $this->attendanceColumns)) {
            $this->attendanceColumns[$column] = Schema::hasColumn('absensis', $column);
        }

        return $this->attendanceColumns[$column];
    }

    private function hasLogTable(): bool
    {
        return Schema::hasTable('log_presensi');
    }

    private function hasLogColumn(string $column): bool
    {
        if (!$this->hasLogTable()) {
            return false;
        }

        if (!array_key_exists($column, $this->logColumns)) {
            $this->logColumns[$column] = Schema::hasColumn('log_presensi', $column);
        }

        return $this->logColumns[$column];
    }

    private function hasVerificationTable(): bool
    {
        return Schema::hasTable('presensi_verifications');
    }

    private function hasVerificationColumn(string $column): bool
    {
        if (!$this->hasVerificationTable()) {
            return false;
        }

        if (!array_key_exists($column, $this->verificationColumns)) {
            $this->verificationColumns[$column] = Schema::hasColumn('presensi_verifications', $column);
        }

        return $this->verificationColumns[$column];
    }
}
