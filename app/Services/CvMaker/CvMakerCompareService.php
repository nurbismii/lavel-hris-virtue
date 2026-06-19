<?php

namespace App\Services\CvMaker;

use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CvMakerCompareService
{
    private const FIELD_GROUPS = [
        'identity' => [
            ['key' => 'name', 'label' => 'Nama', 'hris' => 'nama_karyawan', 'cv' => 'full_name', 'type' => 'text'],
            ['key' => 'birth_date', 'label' => 'Tanggal lahir', 'hris' => 'tgl_lahir', 'cv' => 'birth_date', 'type' => 'date'],
            ['key' => 'gender', 'label' => 'Gender', 'hris' => 'jenis_kelamin', 'cv' => 'gender', 'type' => 'gender'],
            ['key' => 'marital_status', 'label' => 'Status nikah', 'hris' => 'status_perkawinan', 'cv' => 'marital_status', 'type' => 'marital'],
            ['key' => 'phone', 'label' => 'No. HP', 'hris' => 'no_telp', 'cv' => 'phone', 'type' => 'phone'],
            ['key' => 'address', 'label' => 'Alamat', 'hris' => 'address', 'cv' => 'address', 'type' => 'address'],
        ],
        'work' => [
            ['key' => 'work_area', 'label' => 'Perusahaan', 'hris' => 'area_kerja', 'cv' => 'work_area', 'type' => 'text'],
            ['key' => 'department', 'label' => 'Departemen', 'hris' => 'department_name', 'cv' => 'department', 'type' => 'text'],
            ['key' => 'division', 'label' => 'Divisi', 'hris' => 'division_name', 'cv' => 'division', 'type' => 'text'],
            ['key' => 'position', 'label' => 'Posisi', 'hris' => 'position', 'cv' => 'position', 'type' => 'text'],
        ],
        'location' => [
            ['key' => 'province', 'label' => 'Provinsi', 'hris' => 'province_name', 'cv' => 'province_name', 'type' => 'text'],
            ['key' => 'regency', 'label' => 'Kabupaten', 'hris' => 'regency_name', 'cv' => 'regency_name', 'type' => 'text'],
            ['key' => 'district', 'label' => 'Kecamatan', 'hris' => 'district_name', 'cv' => 'district_name', 'type' => 'text'],
            ['key' => 'village', 'label' => 'Kelurahan', 'hris' => 'village_name', 'cv' => 'village_name', 'type' => 'text'],
        ],
        'education' => [
            ['key' => 'education_level', 'label' => 'Pendidikan', 'hris' => 'pendidikan_terakhir', 'cv' => 'education_level', 'type' => 'education_level'],
            ['key' => 'education_institution', 'label' => 'Instansi', 'hris' => 'nama_instansi_pendidikan', 'cv' => 'education_institution', 'type' => 'text'],
            ['key' => 'education_major', 'label' => 'Jurusan', 'hris' => 'jurusan', 'cv' => 'education_major', 'type' => 'text'],
            ['key' => 'graduation_year', 'label' => 'Tahun lulus', 'hris' => 'tanggal_kelulusan', 'cv' => 'graduation_year', 'type' => 'year'],
        ],
    ];

    private const EDUCATION_RANKS = [
        'SD' => 10,
        'SMP' => 20,
        'SMA' => 30,
        'SMK' => 30,
        'D1' => 40,
        'D2' => 50,
        'D3' => 60,
        'D4' => 70,
        'S1' => 80,
        'S2' => 90,
        'S3' => 100,
    ];

    private const UPDATE_FIELDS = [
        ['key' => 'name', 'label' => 'Nama', 'column' => 'nama_karyawan', 'cv' => 'full_name', 'type' => 'text', 'max' => 255],
        ['key' => 'birth_date', 'label' => 'Tanggal lahir', 'column' => 'tgl_lahir', 'cv' => 'birth_date', 'type' => 'date'],
        ['key' => 'gender', 'label' => 'Gender', 'column' => 'jenis_kelamin', 'cv' => 'gender', 'type' => 'gender'],
        ['key' => 'marital_status', 'label' => 'Status nikah', 'column' => 'status_perkawinan', 'cv' => 'marital_status', 'type' => 'marital'],
        ['key' => 'phone', 'label' => 'No. HP', 'column' => 'no_telp', 'cv' => 'phone', 'type' => 'phone', 'max' => 20],
        ['key' => 'address', 'label' => 'Alamat domisili', 'column' => 'alamat_domisili', 'cv' => 'address', 'type' => 'text', 'max' => 500],
        ['key' => 'position', 'label' => 'Posisi', 'column' => 'posisi', 'cv' => 'position', 'type' => 'text', 'max' => 255],
        ['key' => 'province', 'label' => 'Provinsi', 'column' => 'provinsi_id', 'cv' => 'province_id', 'cv_label' => 'province_name', 'hris_label' => 'province_name', 'type' => 'id'],
        ['key' => 'regency', 'label' => 'Kabupaten', 'column' => 'kabupaten_id', 'cv' => 'regency_id', 'cv_label' => 'regency_name', 'hris_label' => 'regency_name', 'type' => 'id'],
        ['key' => 'district', 'label' => 'Kecamatan', 'column' => 'kecamatan_id', 'cv' => 'district_id', 'cv_label' => 'district_name', 'hris_label' => 'district_name', 'type' => 'id'],
        ['key' => 'village', 'label' => 'Kelurahan', 'column' => 'kelurahan_id', 'cv' => 'village_id', 'cv_label' => 'village_name', 'hris_label' => 'village_name', 'type' => 'id'],
        ['key' => 'education_level', 'label' => 'Pendidikan', 'column' => 'pendidikan_terakhir', 'cv' => 'education_level', 'type' => 'text', 'max' => 80],
        ['key' => 'education_institution', 'label' => 'Instansi pendidikan', 'column' => 'nama_instansi_pendidikan', 'cv' => 'education_institution', 'type' => 'text', 'max' => 255],
        ['key' => 'education_major', 'label' => 'Jurusan', 'column' => 'jurusan', 'cv' => 'education_major', 'type' => 'text', 'max' => 120],
        ['key' => 'graduation_year', 'label' => 'Tahun lulus', 'column' => 'tanggal_kelulusan', 'cv' => 'graduation_year', 'type' => 'year'],
    ];

    public function isConfigured(): bool
    {
        return filled(config('database.connections.cv_maker.database'))
            && filled(config('services.cv_maker.nik_hash_key'));
    }

    public function datatable(Request $request, User $user): array
    {
        $draw = max(0, (int) $request->input('draw', 0));
        $start = max(0, (int) $request->input('start', 0));
        $maxLength = max(10, (int) config('services.cv_maker.max_page_size', 100));
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, $maxLength);

        $baseQuery = $this->employeeBaseQuery($user);
        $recordsTotal = (clone $baseQuery)->count('employees.nik');

        $filteredQuery = $this->applyFilters(clone $baseQuery, $request);
        $recordsFiltered = (clone $filteredQuery)->count('employees.nik');

        $employees = $this->applyOrdering($filteredQuery, $request)
            ->skip($start)
            ->take($length)
            ->get();

        $cvProfiles = $this->fetchCvProfilesForEmployees($employees);

        $rows = $employees
            ->map(function (Employee $employee) use ($cvProfiles) {
                $cvProfile = $cvProfiles[$employee->nik] ?? null;
                $comparison = $this->compareEmployee($employee, $cvProfile);

                return [
                    'nik' => e($employee->nik),
                    'employee' => $this->renderEmployeeCell($employee),
                    'cv_status' => $this->renderCvStatus($cvProfile),
                    'mismatch_summary' => $this->renderMismatchSummary($comparison, $cvProfile),
                    'identity' => $this->renderComparisonGroup($comparison['groups']['identity']),
                    'work' => $this->renderComparisonGroup($comparison['groups']['work']),
                    'location' => $this->renderComparisonGroup($comparison['groups']['location']),
                    'education' => $this->renderComparisonGroup($comparison['groups']['education']),
                    'action' => $this->renderActionCell($employee, $cvProfile),
                ];
            })
            ->values()
            ->all();

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
            'integration_available' => $this->isConfigured(),
        ];
    }

    public function compareEmployee(Employee $employee, ?array $cvProfile): array
    {
        $groups = [];
        $mismatchCount = 0;
        $comparedCount = 0;

        foreach (self::FIELD_GROUPS as $group => $fields) {
            $groups[$group] = [];

            foreach ($fields as $field) {
                $result = $this->compareField(
                    $field['label'],
                    $this->employeeComparableValue($employee, $field['hris']),
                    $cvProfile ? ($cvProfile[$field['cv']] ?? null) : null,
                    $field['type']
                );

                if (!$result['skipped']) {
                    $comparedCount++;
                }

                if ($result['mismatch']) {
                    $mismatchCount++;
                }

                $groups[$group][] = $result;
            }
        }

        return [
            'groups' => $groups,
            'mismatch_count' => $mismatchCount,
            'compared_count' => $comparedCount,
        ];
    }

    public function compareField(string $label, $hrisValue, $cvValue, string $type = 'text'): array
    {
        $hrisNormalized = $this->normalizeForCompare($hrisValue, $type);
        $cvNormalized = $this->normalizeForCompare($cvValue, $type);
        $skipped = $hrisNormalized === null || $cvNormalized === null;
        $mismatch = !$skipped && $hrisNormalized !== $cvNormalized;

        return [
            'label' => $label,
            'hris' => $this->displayValue($hrisValue, $type),
            'cv' => $this->displayValue($cvValue, $type),
            'skipped' => $skipped,
            'mismatch' => $mismatch,
        ];
    }

    public function normalizeForCompare($value, string $type = 'text'): ?string
    {
        if ($value instanceof Carbon) {
            $value = $value->toDateString();
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        switch ($type) {
            case 'date':
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (Throwable $exception) {
                    return null;
                }

            case 'year':
                if (preg_match('/(19|20)\d{2}/', $value, $match)) {
                    return $match[0];
                }

                return null;

            case 'id':
                return ctype_digit($value) ? (string) ((int) $value) : null;

            case 'phone':
                $digits = preg_replace('/\D+/', '', $value) ?: '';

                if (strpos($digits, '62') === 0) {
                    $digits = '0' . substr($digits, 2);
                }

                return $digits !== '' ? $digits : null;

            case 'gender':
                $clean = $this->normalizeText($value);

                if (in_array($clean, ['L', 'LAKI LAKI', 'LAKILAKI', 'M', 'MALE'], true)) {
                    return 'L';
                }

                if (in_array($clean, ['P', 'PEREMPUAN', 'F', 'FEMALE', 'WANITA'], true)) {
                    return 'P';
                }

                return $clean ?: null;

            case 'marital':
                $clean = $this->normalizeText($value);

                if (in_array($clean, ['BELUM', 'BELUM KAWIN', 'BELUM MENIKAH', 'TK', 'SINGLE'], true)) {
                    return 'BELUM';
                }

                if (in_array($clean, ['KAWIN', 'MENIKAH', 'MARRIED'], true)) {
                    return 'KAWIN';
                }

                if (strpos($clean, 'CERAI') === 0) {
                    return 'CERAI';
                }

                return $clean ?: null;

            case 'education_level':
                $clean = $this->normalizeText($value);
                $clean = str_replace([' ', '.', '-'], '', $clean);

                if ($clean === 'SLTA') {
                    return 'SMA';
                }

                return $clean ?: null;

            case 'address':
            case 'text':
            default:
                return $this->normalizeText($value) ?: null;
        }
    }

    public function hashNik(string $nik): string
    {
        return hash_hmac('sha256', $nik, (string) config('services.cv_maker.nik_hash_key'));
    }

    public function previewUpdateForEmployee(Employee $employee): array
    {
        $employee->loadMissing(['departemen', 'divisi', 'provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);
        $cvProfile = $this->cvProfileForEmployee($employee);

        return $this->buildUpdatePreview($employee, $cvProfile);
    }

    public function updateHrisFromCv(Employee $employee, User $actor): array
    {
        $employee->loadMissing(['departemen', 'divisi', 'provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);
        $cvProfile = $this->cvProfileForEmployee($employee);
        $preview = $this->buildUpdatePreview($employee, $cvProfile);

        if (!$preview['success'] || empty($preview['changes'])) {
            return $preview;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($preview['changes'] as $change) {
            $oldValues[$change['column']] = $change['old_raw'];
            $newValues[$change['column']] = $change['new_raw'];
        }

        DB::transaction(function () use ($employee, $actor, $oldValues, $newValues, $preview) {
            $employee->forceFill($newValues)->save();

            app(AuditTrailService::class)->record([
                'event' => 'cv_maker.hris_updated',
                'module' => 'cv_maker_compare',
                'auditable_type' => Employee::class,
                'auditable_id' => (string) $employee->nik,
                'reference_table' => 'employees',
                'reference_id' => (string) $employee->nik,
                'employee_nik' => (string) $employee->nik,
                'actor' => $actor,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => [
                    'source' => 'cv_maker',
                    'cv_user_id' => $preview['cv_profile']['user_id'] ?? null,
                    'cv_profile_id' => $preview['cv_profile']['profile_id'] ?? null,
                    'changed_fields' => collect($preview['changes'])->pluck('column')->values()->all(),
                ],
                'note' => 'Update data V-People dari CV Maker.',
            ]);
        });

        return array_merge($preview, [
            'updated' => true,
            'message' => count($preview['changes']) . ' field berhasil diperbarui dari CV Maker.',
        ]);
    }

    public function buildUpdatePreview(Employee $employee, ?array $cvProfile): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Koneksi CV Maker belum dikonfigurasi.',
                'changes' => [],
                'skipped' => [],
            ];
        }

        if (!$cvProfile || empty($cvProfile['profile_id'])) {
            return [
                'success' => false,
                'message' => 'Profil CV Maker tidak ditemukan untuk karyawan ini.',
                'changes' => [],
                'skipped' => [],
            ];
        }

        $validLocationIds = $this->validCvLocationIds($cvProfile);
        $changes = [];
        $skipped = $validLocationIds['skipped'];

        foreach (self::UPDATE_FIELDS as $field) {
            $cvValue = $cvProfile[$field['cv']] ?? null;
            $newValue = $this->transformCvValueForUpdate($cvValue, $field);

            if ($newValue === null) {
                $hasDisplayOnlyLocation = $field['type'] === 'id'
                    && isset($field['cv_label'])
                    && filled($cvProfile[$field['cv_label']] ?? null);

                if (filled($cvValue) || $hasDisplayOnlyLocation) {
                    $skipped[] = [
                        'label' => $field['label'],
                        'reason' => $field['type'] === 'id'
                            ? 'ID wilayah CV Maker kosong atau tidak valid untuk master HRIS.'
                            : 'Nilai CV Maker tidak valid untuk format HRIS.',
                    ];
                }

                continue;
            }

            if ($field['type'] === 'id' && !in_array($field['column'], $validLocationIds['columns'], true)) {
                continue;
            }

            $oldValue = $employee->{$field['column']};
            $oldNormalized = $this->normalizeForCompare($oldValue, $field['type']);
            $newNormalized = $this->normalizeForCompare($newValue, $field['type']);

            if ($newNormalized === null || $oldNormalized === $newNormalized) {
                continue;
            }

            $changes[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'column' => $field['column'],
                'old_raw' => $oldValue,
                'new_raw' => $newValue,
                'old' => $this->updateDisplayValue($employee, $cvProfile, $field, 'old', $oldValue),
                'new' => $this->updateDisplayValue($employee, $cvProfile, $field, 'new', $newValue),
            ];
        }

        return [
            'success' => true,
            'message' => count($changes) > 0
                ? count($changes) . ' field siap diperbarui.'
                : 'Tidak ada perubahan yang bisa diperbarui dari CV Maker.',
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
            ],
            'cv_profile' => [
                'user_id' => $cvProfile['user_id'] ?? null,
                'profile_id' => $cvProfile['profile_id'] ?? null,
                'status' => $cvProfile['status'] ?? null,
                'updated_at' => $cvProfile['updated_at'] ?? null,
            ],
            'changes' => $changes,
            'skipped' => $skipped,
        ];
    }

    private function employeeBaseQuery(User $user): Builder
    {
        $query = Employee::query()
            ->select([
                'employees.nik',
                'employees.nama_karyawan',
                'employees.tgl_lahir',
                'employees.jenis_kelamin',
                'employees.status_perkawinan',
                'employees.no_telp',
                'employees.alamat_domisili',
                'employees.alamat_ktp',
                'employees.area_kerja',
                'employees.departemen_id',
                'employees.divisi_id',
                'employees.provinsi_id',
                'employees.kabupaten_id',
                'employees.kecamatan_id',
                'employees.kelurahan_id',
                'employees.posisi',
                'employees.jabatan',
                'employees.status_resign',
                'employees.pendidikan_terakhir',
                'employees.nama_instansi_pendidikan',
                'employees.jurusan',
                'employees.tanggal_kelulusan',
            ])
            ->whereNotNull('employees.status_resign')
            ->with([
                'departemen:id,departemen',
                'divisi:id,nama_divisi',
                'provinsi:id,provinsi',
                'kabupaten:id,kabupaten',
                'kecamatan:id,kecamatan',
                'kelurahan:id,kelurahan',
            ]);

        return $user->applyEmployeeScope($query, 'employees');
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $areaCodes = collect((array) $request->input('area'))
            ->filter(fn($value) => filled($value))
            ->map(fn($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        if ($areaCodes) {
            $query->whereIn('employees.area_kerja', $areaCodes);
        }

        if ($request->filled('departemen')) {
            $query->where('employees.departemen_id', $request->input('departemen'));
        }

        if ($request->filled('divisi')) {
            $query->where('employees.divisi_id', $request->input('divisi'));
        }

        if ($request->filled('status_resign')) {
            $query->where('employees.status_resign', $request->input('status_resign'));
        }

        $keyword = trim((string) $request->input('search.value', ''));

        if ($keyword !== '') {
            $query->where(function (Builder $searchQuery) use ($keyword) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';

                $searchQuery
                    ->where('employees.nik', 'like', $like)
                    ->orWhere('employees.nama_karyawan', 'like', $like)
                    ->orWhere('employees.posisi', 'like', $like)
                    ->orWhere('employees.jabatan', 'like', $like)
                    ->orWhere('employees.no_telp', 'like', $like);
            });
        }

        return $query;
    }

    private function applyOrdering(Builder $query, Request $request): Builder
    {
        $columns = [
            0 => 'employees.nik',
            1 => 'employees.nama_karyawan',
            2 => 'employees.status_resign',
        ];
        $columnIndex = (int) $request->input('order.0.column', 1);
        $column = $columns[$columnIndex] ?? 'employees.nama_karyawan';
        $direction = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->orderBy('employees.nik');
    }

    private function cvProfileForEmployee(Employee $employee): ?array
    {
        return $this->fetchCvProfilesForEmployees(collect([$employee]))[$employee->nik] ?? null;
    }

    private function validCvLocationIds(array $cvProfile): array
    {
        $columns = [];
        $skipped = [];
        $provinceId = $this->cleanReferenceId($cvProfile['province_id'] ?? null);
        $regencyId = $this->cleanReferenceId($cvProfile['regency_id'] ?? null);
        $districtId = $this->cleanReferenceId($cvProfile['district_id'] ?? null);
        $villageId = $this->cleanReferenceId($cvProfile['village_id'] ?? null);

        if ($provinceId) {
            $exists = DB::table('master_provinsi')->where('id', $provinceId)->exists();

            if ($exists) {
                $columns[] = 'provinsi_id';
            } else {
                $skipped[] = ['label' => 'Provinsi', 'reason' => 'ID provinsi CV Maker tidak ditemukan di master HRIS.'];
            }
        }

        if ($regencyId) {
            $exists = $provinceId
                ? DB::table('master_kabupaten')->where('id', $regencyId)->where('id_provinsi', $provinceId)->exists()
                : false;

            if ($exists) {
                $columns[] = 'kabupaten_id';
            } else {
                $skipped[] = ['label' => 'Kabupaten', 'reason' => 'ID kabupaten CV Maker tidak sesuai dengan provinsi HRIS.'];
            }
        }

        if ($districtId) {
            $exists = $regencyId
                ? DB::table('master_kecamatan')->where('id', $districtId)->where('id_kabupaten', $regencyId)->exists()
                : false;

            if ($exists) {
                $columns[] = 'kecamatan_id';
            } else {
                $skipped[] = ['label' => 'Kecamatan', 'reason' => 'ID kecamatan CV Maker tidak sesuai dengan kabupaten HRIS.'];
            }
        }

        if ($villageId) {
            $exists = $districtId
                ? DB::table('master_kelurahan')->where('id', $villageId)->where('id_kecamatan', $districtId)->exists()
                : false;

            if ($exists) {
                $columns[] = 'kelurahan_id';
            } else {
                $skipped[] = ['label' => 'Kelurahan', 'reason' => 'ID kelurahan CV Maker tidak sesuai dengan kecamatan HRIS.'];
            }
        }

        return [
            'columns' => $columns,
            'skipped' => $skipped,
        ];
    }

    private function fetchCvProfilesForEmployees(Collection $employees): array
    {
        if (!$this->isConfigured() || $employees->isEmpty()) {
            return [];
        }

        $hashToNik = $employees
            ->pluck('nik')
            ->filter()
            ->mapWithKeys(fn($nik) => [$this->hashNik((string) $nik) => (string) $nik])
            ->all();

        if (empty($hashToNik)) {
            return [];
        }

        try {
            $profiles = DB::connection(config('services.cv_maker.connection', 'cv_maker'))
                ->table('users')
                ->leftJoin('cv_profiles', 'cv_profiles.user_id', '=', 'users.id')
                ->whereIn('users.vpeople_nik_hash', array_keys($hashToNik))
                ->select([
                    'users.id as user_id',
                    'users.email as account_email',
                    'users.vpeople_nik_hash',
                    'users.vpeople_last_synced_at',
                    'cv_profiles.id as profile_id',
                    'cv_profiles.status',
                    'cv_profiles.full_name',
                    'cv_profiles.birth_date',
                    'cv_profiles.gender',
                    'cv_profiles.marital_status',
                    'cv_profiles.province_id',
                    'cv_profiles.province_name',
                    'cv_profiles.regency_id',
                    'cv_profiles.regency_name',
                    'cv_profiles.district_id',
                    'cv_profiles.district_name',
                    'cv_profiles.village_id',
                    'cv_profiles.village_name',
                    'cv_profiles.address',
                    'cv_profiles.phone',
                    'cv_profiles.email',
                    'cv_profiles.work_area',
                    'cv_profiles.department',
                    'cv_profiles.division',
                    'cv_profiles.position',
                    'cv_profiles.updated_at',
                ])
                ->get();
        } catch (Throwable $exception) {
            Log::warning('CV Maker compare lookup failed.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $educationByProfileId = $this->fetchEducationByProfileId($profiles->pluck('profile_id')->filter()->all());

        return $profiles
            ->mapWithKeys(function ($profile) use ($hashToNik, $educationByProfileId) {
                $nik = $hashToNik[$profile->vpeople_nik_hash] ?? null;

                if (!$nik) {
                    return [];
                }

                $education = $educationByProfileId[$profile->profile_id] ?? [];

                return [$nik => [
                    'user_id' => $profile->user_id,
                    'profile_id' => $profile->profile_id,
                    'status' => $profile->status,
                    'account_email' => $profile->account_email,
                    'vpeople_last_synced_at' => $profile->vpeople_last_synced_at,
                    'full_name' => $profile->full_name,
                    'birth_date' => $profile->birth_date,
                    'gender' => $profile->gender,
                    'marital_status' => $profile->marital_status,
                    'province_id' => $profile->province_id,
                    'province_name' => $profile->province_name,
                    'regency_id' => $profile->regency_id,
                    'regency_name' => $profile->regency_name,
                    'district_id' => $profile->district_id,
                    'district_name' => $profile->district_name,
                    'village_id' => $profile->village_id,
                    'village_name' => $profile->village_name,
                    'address' => $profile->address,
                    'phone' => $profile->phone,
                    'email' => $profile->email,
                    'work_area' => $profile->work_area,
                    'department' => $profile->department,
                    'division' => $profile->division,
                    'position' => $profile->position,
                    'updated_at' => $profile->updated_at,
                    'education_level' => $education['level'] ?? null,
                    'education_institution' => $education['institution'] ?? null,
                    'education_major' => $education['major'] ?? null,
                    'graduation_year' => $education['graduation_year'] ?? null,
                ]];
            })
            ->all();
    }

    private function fetchEducationByProfileId(array $profileIds): array
    {
        if (empty($profileIds)) {
            return [];
        }

        try {
            $educations = DB::connection(config('services.cv_maker.connection', 'cv_maker'))
                ->table('cv_educations')
                ->whereIn('cv_profile_id', $profileIds)
                ->select(['cv_profile_id', 'level', 'institution', 'major', 'graduation_year'])
                ->get();
        } catch (Throwable $exception) {
            Log::warning('CV Maker education lookup failed.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        return $educations
            ->groupBy('cv_profile_id')
            ->map(function (Collection $items) {
                $education = $items
                    ->sortByDesc(function ($item) {
                        return ($this->educationRank($item->level) * 10000) + ((int) $item->graduation_year);
                    })
                    ->first();

                return [
                    'level' => $education->level ?? null,
                    'institution' => $education->institution ?? null,
                    'major' => $education->major ?? null,
                    'graduation_year' => $education->graduation_year ?? null,
                ];
            })
            ->all();
    }

    private function employeeComparableValue(Employee $employee, string $key)
    {
        if ($key === 'address') {
            return $employee->alamat_domisili ?: $employee->alamat_ktp;
        }

        if ($key === 'department_name') {
            return optional($employee->departemen)->departemen;
        }

        if ($key === 'division_name') {
            return optional($employee->divisi)->nama_divisi;
        }

        if ($key === 'province_name') {
            return optional($employee->provinsi)->provinsi;
        }

        if ($key === 'regency_name') {
            return optional($employee->kabupaten)->kabupaten;
        }

        if ($key === 'district_name') {
            return optional($employee->kecamatan)->kecamatan;
        }

        if ($key === 'village_name') {
            return optional($employee->kelurahan)->kelurahan;
        }

        if ($key === 'position') {
            return $employee->posisi ?: $employee->jabatan;
        }

        return $employee->{$key};
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/[\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}]+/u', '', $value) ?: $value;
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim(mb_strtoupper($value));
    }

    private function transformCvValueForUpdate($value, array $field)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $type = $field['type'];

        if ($type === 'date') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable $exception) {
                return null;
            }
        }

        if ($type === 'id') {
            return $this->cleanReferenceId($value);
        }

        if ($type === 'year') {
            $year = $this->normalizeForCompare($value, 'year');

            return $year ? Carbon::createFromDate((int) $year, 1, 1)->toDateString() : null;
        }

        if ($type === 'phone') {
            $phone = $this->normalizeForCompare($value, 'phone');

            return $phone && mb_strlen($phone) <= ($field['max'] ?? 20) ? $phone : null;
        }

        if ($type === 'gender') {
            $gender = $this->normalizeForCompare($value, 'gender');

            return in_array($gender, ['L', 'P'], true) ? $gender : null;
        }

        if ($type === 'marital') {
            $marital = $this->normalizeForCompare($value, 'marital');

            if ($marital === 'BELUM') {
                return 'Belum Kawin';
            }

            if ($marital === 'KAWIN') {
                return 'Kawin';
            }

            if ($marital === 'CERAI') {
                return 'Cerai';
            }

            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?: (string) $value);
        $max = $field['max'] ?? 255;

        return $text !== '' && mb_strlen($text) <= $max ? $text : null;
    }

    private function cleanReferenceId($value): ?int
    {
        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    private function updateDisplayValue(Employee $employee, array $cvProfile, array $field, string $side, $fallback): string
    {
        if ($field['type'] === 'id') {
            if ($side === 'old') {
                $label = $this->employeeComparableValue($employee, $field['hris_label']);

                return $label ? (string) $label : $this->plainDisplayValue($fallback, 'id');
            }

            $label = $cvProfile[$field['cv_label']] ?? null;

            return $label ? (string) $label : $this->plainDisplayValue($fallback, 'id');
        }

        return $this->plainDisplayValue($fallback, $field['type']);
    }

    private function plainDisplayValue($value, string $type): string
    {
        if ($value instanceof Carbon) {
            $value = $type === 'year' ? $value->format('Y') : $value->format('Y-m-d');
        }

        if ($value === null || trim((string) $value) === '') {
            return '-';
        }

        if ($type === 'date') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (Throwable $exception) {
                return (string) $value;
            }
        }

        if ($type === 'year') {
            $year = $this->normalizeForCompare($value, 'year');

            return $year ?: (string) $value;
        }

        return (string) $value;
    }

    private function displayValue($value, string $type): string
    {
        if ($value instanceof Carbon) {
            $value = $type === 'year' ? $value->format('Y') : $value->format('Y-m-d');
        }

        if ($value === null || trim((string) $value) === '') {
            return '-';
        }

        if ($type === 'date') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (Throwable $exception) {
                return e((string) $value);
            }
        }

        if ($type === 'year') {
            $year = $this->normalizeForCompare($value, 'year');

            return $year ?: e((string) $value);
        }

        return e((string) $value);
    }

    private function educationRank(?string $level): int
    {
        $normalized = $this->normalizeForCompare($level, 'education_level');

        foreach (self::EDUCATION_RANKS as $key => $rank) {
            if ($normalized && strpos($normalized, $key) === 0) {
                return $rank;
            }
        }

        return 0;
    }

    private function renderEmployeeCell(Employee $employee): string
    {
        $department = optional($employee->departemen)->departemen ?: '-';
        $division = optional($employee->divisi)->nama_divisi ?: '-';

        return '<div class="cv-employee-cell">'
            . '<div class="cv-employee-cell__name">' . e($employee->nama_karyawan ?: '-') . '</div>'
            . '<div class="cv-employee-cell__meta">' . e($employee->area_kerja ?: '-') . ' / ' . e($department) . ' / ' . e($division) . '</div>'
            . '<div class="cv-employee-cell__meta">' . e($employee->posisi ?: ($employee->jabatan ?: '-')) . '</div>'
            . '</div>';
    }

    private function renderCvStatus(?array $cvProfile): string
    {
        if (!$this->isConfigured()) {
            return '<span class="badge bg-warning text-dark">Belum dikonfigurasi</span>';
        }

        if (!$cvProfile) {
            return '<span class="badge bg-secondary">Tidak ada akun CV</span>';
        }

        if (empty($cvProfile['profile_id'])) {
            return '<span class="badge bg-secondary">Profil kosong</span>';
        }

        $status = $cvProfile['status'] ?: 'draft';
        $badgeClass = $status === 'generated'
            ? 'bg-success'
            : ($status === 'submitted' ? 'bg-info text-dark' : 'bg-light text-dark border');
        $updatedAt = $cvProfile['updated_at']
            ? '<div class="cv-status-meta">' . e(Carbon::parse($cvProfile['updated_at'])->format('d/m/Y H:i')) . '</div>'
            : '';

        return '<span class="badge ' . $badgeClass . '">' . e(ucfirst($status)) . '</span>' . $updatedAt;
    }

    private function renderMismatchSummary(array $comparison, ?array $cvProfile): string
    {
        if (!$this->isConfigured() || !$cvProfile || empty($cvProfile['profile_id'])) {
            return '<span class="badge bg-secondary">Diabaikan</span>';
        }

        if ($comparison['compared_count'] < 1) {
            return '<span class="badge bg-secondary">Tidak ada field</span>';
        }

        if ($comparison['mismatch_count'] > 0) {
            return '<span class="badge bg-danger">' . (int) $comparison['mismatch_count'] . ' mismatch</span>'
                . '<div class="cv-status-meta">' . (int) $comparison['compared_count'] . ' field dibandingkan</div>';
        }

        return '<span class="badge bg-success">Sesuai</span>'
            . '<div class="cv-status-meta">' . (int) $comparison['compared_count'] . ' field dibandingkan</div>';
    }

    private function renderComparisonGroup(array $items): string
    {
        $html = '<div class="cv-compare-fields">';

        foreach ($items as $item) {
            $class = $item['mismatch']
                ? ' cv-compare-field--mismatch'
                : ($item['skipped'] ? ' cv-compare-field--skipped' : '');

            $html .= '<div class="cv-compare-field' . $class . '">'
                . '<div class="cv-compare-field__label">' . e($item['label']) . '</div>'
                . '<div class="cv-compare-field__values">'
                . '<span title="HRIS">' . $item['hris'] . '</span>'
                . '<span title="CV Maker">' . $item['cv'] . '</span>'
                . '</div>'
                . '</div>';
        }

        return $html . '</div>';
    }

    private function renderActionCell(Employee $employee, ?array $cvProfile): string
    {
        $editButton = '<a href="' . e(route('karyawan.edit', $employee->nik)) . '" class="btn btn-sm btn-outline-primary ui-btn-icon" title="Edit HRIS">'
            . '<i class="fas fa-edit"></i>'
            . '</a>';

        if (!$this->isConfigured() || !$cvProfile || empty($cvProfile['profile_id'])) {
            return '<div class="cv-action-buttons">' . $editButton . '</div>';
        }

        $updateButton = '<button type="button" class="btn btn-sm btn-outline-danger ui-btn-icon js-cv-update-preview"'
            . ' data-preview-url="' . e(route('cv-maker-compare.preview-update', $employee->nik)) . '"'
            . ' data-update-url="' . e(route('cv-maker-compare.update-hris', $employee->nik)) . '"'
            . ' data-employee-name="' . e($employee->nama_karyawan ?: $employee->nik) . '"'
            . ' title="Update HRIS dari CV Maker">'
            . '<i class="fas fa-sync-alt"></i>'
            . '</button>';

        return '<div class="cv-action-buttons">' . $editButton . $updateButton . '</div>';
    }
}
