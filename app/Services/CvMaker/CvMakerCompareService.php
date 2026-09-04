<?php

namespace App\Services\CvMaker;

use App\Models\CvMakerProgressStatus;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CvMakerCompareService
{
    private const DOCUMENT_LABELS = [
        'ktp' => 'KTP', 'family_card' => 'Kartu Keluarga', 'diploma' => 'Ijazah',
        'certificate' => 'Sertifikat / Pelatihan', 'work_experience' => 'Pengalaman Kerja / Paklaring',
        'other' => 'Dokumen Lainnya', 'npwp' => 'NPWP', 'vaccination_certificate' => 'Sertifikat Vaksin',
        'birth_certificate' => 'Akta Kelahiran', 'marriage_book' => 'Buku Nikah',
        'divorce_certificate' => 'Surat Cerai', 'sim_b2_umum' => 'SIM B2 Umum', 'sio' => 'SIO',
        'k3_certificate' => 'Sertifikat K3', 'security_kta' => 'KTA Security',
    ];

    private $apiClient;

    private $relatedDataService;

    private $organizationSyncService;

    private $apiRelatedRowsByProfileId = [];

    private $jobLevelCodesByTitleId;

    public function __construct(
        CvMakerApiClient $apiClient = null,
        CvMakerRelatedDataService $relatedDataService = null,
        CvMakerOrganizationSyncService $organizationSyncService = null
    )
    {
        $this->apiClient = $apiClient;
        $this->relatedDataService = $relatedDataService;
        $this->organizationSyncService = $organizationSyncService;
    }

    private const FIELD_GROUPS = [
        'identity' => [
            ['key' => 'name', 'label' => 'Nama', 'hris' => 'nama_karyawan', 'cv' => 'full_name', 'type' => 'text'],
            ['key' => 'ktp_number', 'label' => 'No. KTP', 'hris' => 'no_ktp', 'cv' => 'ktp_number', 'type' => 'identity_number'],
            ['key' => 'family_card_number', 'label' => 'No. KK', 'hris' => 'no_kk', 'cv' => 'family_card_number', 'type' => 'identity_number'],
            ['key' => 'birth_date', 'label' => 'Tanggal lahir', 'hris' => 'tgl_lahir', 'cv' => 'birth_date', 'type' => 'date'],
            ['key' => 'gender', 'label' => 'Gender', 'hris' => 'jenis_kelamin', 'cv' => 'gender', 'type' => 'gender'],
            ['key' => 'blood_type', 'label' => 'Golongan darah', 'hris' => 'golongan_darah', 'cv' => 'blood_type', 'type' => 'blood_type'],
            ['key' => 'height', 'label' => 'Tinggi badan', 'hris' => 'tinggi', 'cv' => 'height_cm', 'type' => 'body_measurement'],
            ['key' => 'weight', 'label' => 'Berat badan', 'hris' => 'berat', 'cv' => 'weight_kg', 'type' => 'body_measurement'],
            ['key' => 'religion', 'label' => 'Agama', 'hris' => 'agama', 'cv' => 'religion', 'type' => 'religion'],
            ['key' => 'marital_status', 'label' => 'Status nikah', 'hris' => 'status_perkawinan', 'cv' => 'marital_status', 'type' => 'marital'],
            ['key' => 'phone', 'label' => 'No. HP', 'hris' => 'no_telp', 'cv' => 'phone', 'type' => 'phone'],
        ],
        'family' => [
            ['key' => 'mother_name', 'label' => 'Nama ibu kandung', 'hris' => 'nama_ibu_kandung', 'cv' => 'mother_name', 'type' => 'text'],
            ['key' => 'spouse_name', 'label' => 'Nama suami/istri', 'hris' => 'nama_bapak', 'cv' => 'spouse_name', 'type' => 'text'],
            ['key' => 'marriage_date', 'label' => 'Tanggal menikah', 'hris' => 'tanggal_menikah', 'cv' => 'marriage_date', 'type' => 'date'],
        ],
        'address' => [
            ['key' => 'ktp_address', 'label' => 'Alamat KTP', 'hris' => 'alamat_ktp', 'cv' => 'ktp_address', 'type' => 'address'],
            ['key' => 'rt', 'label' => 'RT', 'hris' => 'rt', 'cv' => 'rt', 'type' => 'address_number'],
            ['key' => 'rw', 'label' => 'RW', 'hris' => 'rw', 'cv' => 'rw', 'type' => 'address_number'],
            ['key' => 'domicile_address', 'label' => 'Alamat domisili', 'hris' => 'alamat_domisili', 'cv' => 'address', 'type' => 'address'],
        ],
        'administration' => [
            ['key' => 'npwp_number', 'label' => 'NPWP', 'hris' => 'npwp', 'cv' => 'npwp_number', 'type' => 'numeric_identifier'],
            ['key' => 'bank_account_number', 'label' => 'Nomor rekening', 'hris' => 'no_rekening', 'cv' => 'bank_account_number', 'type' => 'numeric_identifier'],
        ],
        'work' => [
            ['key' => 'work_area', 'label' => 'Perusahaan', 'hris' => 'area_kerja', 'cv' => 'work_area', 'type' => 'text'],
            ['key' => 'department', 'label' => 'Departemen', 'hris' => 'department_name', 'cv' => 'department', 'type' => 'text'],
            ['key' => 'division', 'label' => 'Divisi', 'hris' => 'division_name', 'cv' => 'division', 'type' => 'text'],
            ['key' => 'job_title', 'label' => 'Jabatan', 'hris' => 'job_title', 'cv' => 'job_title', 'type' => 'text'],
            ['key' => 'position', 'label' => 'Posisi', 'hris' => 'position', 'cv' => 'position', 'type' => 'text'],
            ['key' => 'job_title_master', 'label' => 'ID Master Jabatan', 'hris' => 'job_title_id', 'cv' => 'job_title_id', 'type' => 'id'],
            ['key' => 'job_level', 'label' => 'Level Jabatan', 'hris' => 'job_level_code', 'cv' => 'job_level_code', 'type' => 'text'],
            ['key' => 'entry_date', 'label' => 'Tanggal masuk', 'hris' => 'entry_date', 'cv' => 'current_job_entry_date', 'type' => 'date'],
        ],
        'location' => [
            ['key' => 'province', 'label' => 'Provinsi', 'hris' => 'province_name', 'cv' => 'province_name', 'type' => 'text', 'compare_missing' => true],
            ['key' => 'regency', 'label' => 'Kabupaten', 'hris' => 'regency_name', 'cv' => 'regency_name', 'type' => 'text', 'compare_missing' => true],
            ['key' => 'district', 'label' => 'Kecamatan', 'hris' => 'district_name', 'cv' => 'district_name', 'type' => 'text', 'compare_missing' => true],
            ['key' => 'village', 'label' => 'Kelurahan', 'hris' => 'village_name', 'cv' => 'village_name', 'type' => 'text', 'compare_missing' => true],
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
        ['key' => 'ktp_number', 'label' => 'No. KTP', 'column' => 'no_ktp', 'cv' => 'ktp_number', 'type' => 'identity_number'],
        ['key' => 'family_card_number', 'label' => 'No. KK', 'column' => 'no_kk', 'cv' => 'family_card_number', 'type' => 'identity_number'],
        ['key' => 'birth_date', 'label' => 'Tanggal lahir', 'column' => 'tgl_lahir', 'cv' => 'birth_date', 'type' => 'date'],
        ['key' => 'gender', 'label' => 'Gender', 'column' => 'jenis_kelamin', 'cv' => 'gender', 'type' => 'gender'],
        ['key' => 'blood_type', 'label' => 'Golongan darah', 'column' => 'golongan_darah', 'cv' => 'blood_type', 'type' => 'blood_type', 'max' => 8],
        ['key' => 'height', 'label' => 'Tinggi badan', 'column' => 'tinggi', 'cv' => 'height_cm', 'type' => 'body_measurement', 'max' => 4],
        ['key' => 'weight', 'label' => 'Berat badan', 'column' => 'berat', 'cv' => 'weight_kg', 'type' => 'body_measurement', 'max' => 4],
        ['key' => 'religion', 'label' => 'Agama', 'column' => 'agama', 'cv' => 'religion', 'type' => 'religion', 'max' => 50],
        ['key' => 'marital_status', 'label' => 'Status nikah', 'column' => 'status_perkawinan', 'cv' => 'marital_status', 'type' => 'marital'],
        ['key' => 'mother_name', 'label' => 'Nama ibu kandung', 'column' => 'nama_ibu_kandung', 'cv' => 'mother_name', 'type' => 'text', 'max' => 180],
        ['key' => 'spouse_name', 'label' => 'Nama suami/istri', 'column' => 'nama_bapak', 'cv' => 'spouse_name', 'type' => 'text', 'max' => 180],
        ['key' => 'marriage_date', 'label' => 'Tanggal menikah', 'column' => 'tanggal_menikah', 'cv' => 'marriage_date', 'type' => 'date'],
        ['key' => 'phone', 'label' => 'No. HP', 'column' => 'no_telp', 'cv' => 'phone', 'type' => 'phone', 'max' => 20],
        ['key' => 'ktp_address', 'label' => 'Alamat KTP', 'column' => 'alamat_ktp', 'cv' => 'ktp_address', 'type' => 'address', 'max' => 500],
        ['key' => 'rt', 'label' => 'RT', 'column' => 'rt', 'cv' => 'rt', 'type' => 'address_number', 'max' => 3],
        ['key' => 'rw', 'label' => 'RW', 'column' => 'rw', 'cv' => 'rw', 'type' => 'address_number', 'max' => 3],
        ['key' => 'domicile_address', 'label' => 'Alamat domisili', 'column' => 'alamat_domisili', 'cv' => 'address', 'type' => 'address', 'max' => 500],
        ['key' => 'npwp_number', 'label' => 'NPWP', 'column' => 'npwp', 'cv' => 'npwp_number', 'type' => 'numeric_identifier', 'max' => 32],
        ['key' => 'bank_account_number', 'label' => 'Nomor rekening', 'column' => 'no_rekening', 'cv' => 'bank_account_number', 'type' => 'numeric_identifier', 'max' => 64],
        ['key' => 'job_title', 'label' => 'Jabatan', 'column' => 'jabatan', 'cv' => 'job_title', 'type' => 'text', 'max' => 255],
        ['key' => 'position', 'label' => 'Posisi', 'column' => 'posisi', 'cv' => 'position', 'type' => 'text', 'max' => 255],
        ['key' => 'entry_date', 'label' => 'Tanggal masuk', 'column' => 'entry_date', 'cv' => 'current_job_entry_date', 'type' => 'date'],
        ['key' => 'province', 'label' => 'Provinsi', 'column' => 'provinsi_id', 'cv' => 'province_id', 'cv_label' => 'province_name', 'hris_label' => 'province_name', 'type' => 'id'],
        ['key' => 'regency', 'label' => 'Kabupaten', 'column' => 'kabupaten_id', 'cv' => 'regency_id', 'cv_label' => 'regency_name', 'hris_label' => 'regency_name', 'type' => 'id'],
        ['key' => 'district', 'label' => 'Kecamatan', 'column' => 'kecamatan_id', 'cv' => 'district_id', 'cv_label' => 'district_name', 'hris_label' => 'district_name', 'type' => 'id'],
        ['key' => 'village', 'label' => 'Kelurahan', 'column' => 'kelurahan_id', 'cv' => 'village_id', 'cv_label' => 'village_name', 'hris_label' => 'village_name', 'type' => 'id'],
        ['key' => 'education_level', 'label' => 'Pendidikan', 'column' => 'pendidikan_terakhir', 'cv' => 'education_level', 'type' => 'text', 'max' => 80],
        ['key' => 'education_institution', 'label' => 'Instansi pendidikan', 'column' => 'nama_instansi_pendidikan', 'cv' => 'education_institution', 'type' => 'text', 'max' => 255],
        ['key' => 'education_major', 'label' => 'Jurusan', 'column' => 'jurusan', 'cv' => 'education_major', 'type' => 'text', 'max' => 120],
        ['key' => 'graduation_year', 'label' => 'Tahun lulus', 'column' => 'tanggal_kelulusan', 'cv' => 'graduation_year', 'type' => 'year'],
    ];

    private const MANUAL_CORRECTION_FIELDS = [
        'name', 'ktp_number', 'family_card_number', 'birth_date', 'gender', 'blood_type',
        'height', 'weight', 'religion', 'marital_status', 'mother_name', 'spouse_name',
        'marriage_date', 'phone', 'ktp_address', 'rt', 'rw', 'domicile_address',
        'npwp_number', 'bank_account_number', 'entry_date', 'education_level',
        'education_institution', 'education_major', 'graduation_year',
    ];

    private const CV_PROFILE_COLUMNS = [
        'status',
        'full_name',
        'photo_path',
        'birth_place',
        'birth_date',
        'ktp_number',
        'family_card_number',
        'bank_account_number',
        'npwp_number',
        'gender',
        'height_cm',
        'weight_kg',
        'blood_type',
        'religion',
        'marital_status',
        'marriage_date',
        'spouse_name',
        'mother_name',
        'ktp_address',
        'rt',
        'rw',
        'domicile_same_as_ktp',
        'has_children',
        'children_names',
        'province_id',
        'province_name',
        'regency_id',
        'regency_name',
        'district_id',
        'district_name',
        'village_id',
        'village_name',
        'address',
        'phone',
        'email',
        'instagram',
        'linkedin',
        'facebook',
        'work_area',
        'department',
        'division',
        'job_title',
        'position',
        'job_title_id',
        'organization_position_id',
        'job_level_code',
        'job_level_rank',
        'organization_updated_at',
        'current_job_entry_date',
        'profile_summary',
        'technical_skills',
        'non_technical_skills',
        'hobbies',
        'other_hobby',
        'talents',
        'other_talent',
        'last_generated_at',
        'updated_at',
    ];

    public function isConfigured(): bool
    {
        $transport = $this->transport();
        $apiConfigured = $this->apiClient()->isConfigured();
        $databaseConfigured = filled(config('database.connections.cv_maker.database'))
            && filled(config('services.cv_maker.nik_hash_key'));

        if ($transport === 'api') {
            return $apiConfigured;
        }

        return $transport === 'auto'
            ? ($apiConfigured || $databaseConfigured)
            : $databaseConfigured;
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
        $progressStatuses = $this->fetchProgressStatusesForEmployees($employees);

        $rows = $employees
            ->map(function (Employee $employee) use ($cvProfiles, $progressStatuses) {
                $cvProfile = $cvProfiles[$employee->nik] ?? null;
                $progressStatus = $progressStatuses[$employee->nik] ?? null;
                $comparison = $this->compareEmployee($employee, $cvProfile);

                return [
                    'select' => $progressStatus && $progressStatus->needs_reminder
                        ? '<input type="checkbox" class="form-check-input js-cv-reminder-row" value="' . e($employee->nik) . '" aria-label="Pilih ' . e($employee->nama_karyawan ?: $employee->nik) . '">'
                        : '',
                    'nik' => e($employee->nik),
                    'employee' => $this->renderEmployeeCell($employee),
                    'cv_status' => $this->renderCvStatus($cvProfile, $progressStatus),
                    'result' => $this->renderResultCell($employee, $comparison, $cvProfile),
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

    public function filteredEmployeeQuery(Request $request, User $user): Builder
    {
        return $this->applyFilters($this->employeeBaseQuery($user), $request);
    }

    public function detailForEmployee(Employee $employee): array
    {
        $employee->loadMissing(['departemen', 'divisi', 'jobTitle.level', 'organizationPosition.levelOverride', 'organizationPosition.jobTitle.level', 'provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);

        $cvProfile = $this->cvProfileForEmployee($employee);
        $progressStatus = CvMakerProgressStatus::query()
            ->where('employee_nik', $employee->nik)
            ->with(['reviewer', 'histories' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->first();
        $comparison = $this->compareEmployee($employee, $cvProfile);
        $relatedPreview = $cvProfile && !empty($cvProfile['profile_id'])
            ? $this->relatedDataService()->preview(
                (string) $employee->nik,
                (int) $cvProfile['profile_id'],
                $this->cvRelatedSections((int) $cvProfile['profile_id'])
            )
            : ['comparison' => []];

        return [
            'cv_profile' => $cvProfile,
            'progress_status' => $progressStatus,
            'progress_html' => $this->renderProgressSnapshot($progressStatus),
            'progress_histories' => $progressStatus ? $progressStatus->histories : collect(),
            'vitae' => $this->buildVitaeView($cvProfile),
            'comparison' => $comparison,
            'related_comparison' => $relatedPreview['comparison'] ?? [],
            'cv_status' => $this->renderCvStatus($cvProfile, $progressStatus),
            'summary' => $this->renderMismatchSummary($comparison, $cvProfile),
            'can_update' => $this->isConfigured() && $cvProfile && !empty($cvProfile['profile_id']),
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
                    $this->cvComparableValue($cvProfile, $field),
                    $field['type'],
                    !empty($cvProfile['profile_id']) && !empty($field['compare_missing'])
                );

                if (!$result['skipped']) {
                    $comparedCount++;
                }

                if ($result['mismatch']) {
                    $mismatchCount++;
                }

                $result['key'] = $field['key'];
                $result = array_merge($result, $this->manualCorrectionMeta($employee, $field['key']));
                $result = array_merge($result, $this->singleFieldUpdateMeta($field['key'], $result));
                $groups[$group][] = $result;
            }
        }

        return [
            'groups' => $groups,
            'mismatch_count' => $mismatchCount,
            'compared_count' => $comparedCount,
        ];
    }

    public function compareField(string $label, $hrisValue, $cvValue, string $type = 'text', bool $compareMissing = false): array
    {
        $hrisNormalized = $this->normalizeForCompare($hrisValue, $type);
        $cvNormalized = $this->normalizeForCompare($cvValue, $type);
        $skipped = $compareMissing
            ? $hrisNormalized === null && $cvNormalized === null
            : ($hrisNormalized === null || $cvNormalized === null);
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

            case 'body_measurement':
                return $this->normalizeMeasurement($value);

            case 'blood_type':
                return $this->normalizeBloodType($value);

            case 'identity_number':
                $digits = preg_replace('/\D+/', '', $value) ?: '';

                return strlen($digits) === 16 ? $digits : null;

            case 'numeric_identifier':
                $digits = preg_replace('/\D+/', '', $value) ?: '';

                return $digits !== '' ? $digits : null;

            case 'address_number':
                $digits = preg_replace('/\D+/', '', $value) ?: '';

                return $digits !== '' ? str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT) : null;

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

            case 'religion':
                return $this->normalizeReligion($value);

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
        $employee->loadMissing(['departemen', 'divisi', 'jobTitle.level', 'organizationPosition.levelOverride', 'organizationPosition.jobTitle.level', 'provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);
        $cvProfile = $this->cvProfileForEmployee($employee);

        return $this->buildUpdatePreview($employee, $cvProfile);
    }

    public function updateHrisFromCv(
        Employee $employee,
        User $actor,
        ?array $selectedFieldKeys = null,
        ?array $selectedRelatedKeys = null,
        ?bool $syncOrganization = null
    ): array
    {
        $employee->loadMissing(['departemen', 'divisi', 'jobTitle.level', 'organizationPosition.levelOverride', 'organizationPosition.jobTitle.level', 'provinsi', 'kabupaten', 'kecamatan', 'kelurahan']);
        $cvProfile = $this->cvProfileForEmployee($employee);
        $preview = $this->buildUpdatePreview($employee, $cvProfile);

        if (
            !$preview['success']
            || (
                empty($preview['changes'])
                && empty($preview['related_changes'])
                && empty($preview['organization_changes'])
            )
        ) {
            return $preview;
        }

        $selection = $this->selectedUpdateChanges(
            $preview,
            $selectedFieldKeys,
            $selectedRelatedKeys,
            $syncOrganization
        );
        $selectedChanges = $selection['changes'];
        $selectedRelatedChanges = $selection['related_changes'];
        $hasOrganizationChange = $selection['has_organization_change'];

        if (empty($selectedChanges) && empty($selectedRelatedChanges) && !$hasOrganizationChange) {
            return array_merge($preview, [
                'success' => false,
                'message' => 'Pilihan tidak lagi memiliki perubahan. Muat ulang preview lalu coba kembali.',
            ]);
        }

        $oldValues = [];
        $newValues = [];

        foreach ($selectedChanges as $change) {
            $oldValues[$change['column']] = $change['old_raw'];
            $newValues[$change['column']] = $change['new_raw'];
        }

        $organizationResult = DB::transaction(function () use (
            $employee,
            $actor,
            $oldValues,
            $newValues,
            $selectedChanges,
            $selectedRelatedChanges,
            $hasOrganizationChange,
            $preview,
            $cvProfile
        ) {
            if ($newValues) {
                $employee->forceFill($newValues)->save();
            }

            $this->relatedDataService()->sync((string) $employee->nik, $selectedRelatedChanges);
            $organizationResult = $hasOrganizationChange
                ? $this->organizationSyncService()->sync($employee, $cvProfile, $actor)
                : ['synced' => false, 'reason' => 'Sinkronisasi organisasi tidak dipilih.'];

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
                    'changed_fields' => collect($selectedChanges)->pluck('column')->values()->all(),
                    'changed_sections' => collect($selectedRelatedChanges)->pluck('key')->values()->all(),
                    'organization_selected' => $hasOrganizationChange,
                    'organization_sync' => $organizationResult,
                ],
                'note' => 'Update data V-People dari CV Maker.',
            ]);

            return $organizationResult;
        });

        return array_merge($preview, [
            'updated' => true,
            'organization_result' => $organizationResult,
            'message' => count($selectedChanges) . ' field dan '
                . count($selectedRelatedChanges) . ' bagian riwayat berhasil diperbarui dari CV Maker'
                . (!empty($organizationResult['synced']) ? ', termasuk struktur organisasi.' : '.'),
        ]);
    }

    public function correctHrisField(Employee $employee, User $actor, string $fieldKey, $value): array
    {
        $prepared = $this->prepareManualCorrection($fieldKey, $value);

        return DB::transaction(function () use ($employee, $actor, $prepared) {
            $lockedEmployee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $field = $prepared['field'];
            $oldValue = $lockedEmployee->{$field['column']};
            $newValue = $prepared['value'];

            if (
                $this->normalizeForCompare($oldValue, $field['type'])
                === $this->normalizeForCompare($newValue, $field['type'])
            ) {
                return [
                    'success' => true,
                    'updated' => false,
                    'message' => 'Nilai HRIS tidak berubah.',
                    'data' => ['field_key' => $field['key']],
                ];
            }

            $lockedEmployee->forceFill([$field['column'] => $newValue])->save();

            app(AuditTrailService::class)->record([
                'event' => 'cv_maker.hris_field_corrected',
                'module' => 'cv_maker_compare',
                'auditable_type' => Employee::class,
                'auditable_id' => (string) $lockedEmployee->nik,
                'reference_table' => 'employees',
                'reference_id' => (string) $lockedEmployee->nik,
                'employee_nik' => (string) $lockedEmployee->nik,
                'actor' => $actor,
                'old_values' => [$field['column'] => $oldValue],
                'new_values' => [$field['column'] => $newValue],
                'metadata' => [
                    'source' => 'cv_maker_compare_manual_correction',
                    'field_key' => $field['key'],
                ],
                'note' => 'Koreksi satu field HRIS dari halaman detail Compare CV Maker.',
            ]);

            return [
                'success' => true,
                'updated' => true,
                'message' => $field['label'] . ' berhasil dikoreksi.',
                'data' => [
                    'field_key' => $field['key'],
                    'display_value' => $this->plainDisplayValue($newValue, $field['type']),
                ],
            ];
        });
    }

    private function prepareManualCorrection(string $fieldKey, $value): array
    {
        $field = collect(self::UPDATE_FIELDS)->firstWhere('key', $fieldKey);

        if (!$field || !in_array($fieldKey, self::MANUAL_CORRECTION_FIELDS, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'field_key' => 'Field ini tidak dapat dikoreksi langsung dari halaman Compare CV Maker.',
            ]);
        }

        $transformed = $this->transformCvValueForUpdate($value, $field);

        if ($transformed === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'Nilai koreksi tidak sesuai format field ' . $field['label'] . '.',
            ]);
        }

        if (in_array($fieldKey, ['ktp_number', 'family_card_number'], true) && !preg_match('/^\d{16}$/', (string) $transformed)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => $field['label'] . ' harus terdiri dari tepat 16 digit angka.',
            ]);
        }

        return ['field' => $field, 'value' => $transformed];
    }

    private function manualCorrectionMeta(Employee $employee, string $fieldKey): array
    {
        $field = collect(self::UPDATE_FIELDS)->firstWhere('key', $fieldKey);

        if (!$field || !in_array($fieldKey, self::MANUAL_CORRECTION_FIELDS, true)) {
            return ['editable' => false];
        }

        $value = $employee->{$field['column']};
        $sensitive = in_array($field['type'], ['identity_number', 'numeric_identifier'], true);

        if ($value instanceof Carbon) {
            $value = $field['type'] === 'year' ? $value->format('Y') : $value->format('Y-m-d');
        } elseif ($field['type'] === 'year' && filled($value)) {
            $value = $this->normalizeForCompare($value, 'year');
        } elseif ($field['type'] === 'gender' && filled($value)) {
            $value = $this->normalizeForCompare($value, 'gender');
        } elseif ($field['type'] === 'marital' && filled($value)) {
            $marital = $this->normalizeForCompare($value, 'marital');
            $value = [
                'BELUM' => 'Belum Kawin',
                'KAWIN' => 'Kawin',
                'CERAI' => 'Cerai',
            ][$marital] ?? '';
        }

        return [
            'editable' => true,
            'edit_value' => $sensitive ? '' : ($value === null ? '' : (string) $value),
            'input_type' => $field['type'] === 'date'
                ? 'date'
                : (in_array($field['type'], ['year', 'body_measurement'], true) ? 'number' : 'text'),
            'sensitive' => $sensitive,
        ];
    }

    private function singleFieldUpdateMeta(string $fieldKey, array $comparison): array
    {
        $field = collect(self::UPDATE_FIELDS)->firstWhere('key', $fieldKey);
        $highRiskKeys = [
            'ktp_number', 'family_card_number', 'npwp_number', 'bank_account_number',
            'job_title', 'position', 'province', 'regency', 'district', 'village',
        ];

        return [
            'updatable_from_cv' => (bool) $field
                && !empty($comparison['mismatch'])
                && empty($comparison['skipped']),
            'update_from_cv_high_risk' => in_array($fieldKey, $highRiskKeys, true),
        ];
    }

    private function selectedUpdateChanges(
        array $preview,
        ?array $selectedFieldKeys,
        ?array $selectedRelatedKeys,
        ?bool $syncOrganization
    ): array {
        $changes = collect($preview['changes'] ?? [])
            ->filter(fn(array $change) => $selectedFieldKeys === null || in_array($change['key'], $selectedFieldKeys, true))
            ->values()
            ->all();
        $relatedChanges = collect($preview['related_changes'] ?? [])
            ->filter(fn(array $change) => $selectedRelatedKeys === null || in_array($change['key'], $selectedRelatedKeys, true))
            ->values()
            ->all();
        $organizationRequested = $syncOrganization === null ? true : $syncOrganization;

        return [
            'changes' => $changes,
            'related_changes' => $relatedChanges,
            'has_organization_change' => $organizationRequested && !empty($preview['organization_changes']),
        ];
    }

    public function buildUpdatePreview(Employee $employee, ?array $cvProfile): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Koneksi CV Maker belum dikonfigurasi.',
                'changes' => [],
                'related_changes' => [],
                'skipped' => [],
            ];
        }

        if (!$cvProfile || empty($cvProfile['profile_id'])) {
            return [
                'success' => false,
                'message' => 'Profil CV Maker tidak ditemukan untuk karyawan ini.',
                'changes' => [],
                'related_changes' => [],
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

        $relatedPreview = $this->relatedDataService()->preview(
            (string) $employee->nik,
            (int) $cvProfile['profile_id'],
            $this->cvRelatedSections((int) $cvProfile['profile_id'])
        );
        $organizationPreview = $this->organizationSyncService()->preview($employee, $cvProfile);
        $skipped = array_merge($skipped, $relatedPreview['skipped'], $organizationPreview['skipped']);
        $totalChanges = count($changes) + count($relatedPreview['changes']) + count($organizationPreview['changes']);

        return [
            'success' => true,
            'message' => $totalChanges > 0
                ? count($changes) . ' field dan ' . count($relatedPreview['changes']) . ' bagian riwayat siap diperbarui.'
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
            'related_changes' => $relatedPreview['changes'],
            'organization_changes' => $organizationPreview['changes'],
            'skipped' => $skipped,
        ];
    }

    private function cvRelatedSections(int $profileId): array
    {
        return [
            'educations' => $this->fetchCvRelatedRows($profileId, 'cv_educations', [
                'id', 'level', 'institution', 'major', 'graduation_year', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
            'experiences' => $this->fetchCvRelatedRows($profileId, 'cv_experiences', [
                'id', 'position', 'company', 'department', 'division', 'start_month', 'end_month',
                'is_current', 'responsibilities', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
            'organizations' => $this->fetchCvRelatedRows($profileId, 'cv_organizations', [
                'id', 'organization_name', 'role', 'start_year', 'end_year', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
            'certifications' => $this->fetchCvRelatedRows($profileId, 'cv_certifications', [
                'id', 'name', 'issuer', 'year', 'valid_until_year', 'is_lifetime', 'type', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
            'languages' => $this->fetchCvRelatedRows($profileId, 'cv_languages', [
                'id', 'language', 'level', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
            'projects' => $this->fetchCvRelatedRows($profileId, 'cv_projects', [
                'id', 'name', 'year', 'sort_order', 'updated_at',
            ])->map(fn($row) => (array) $row)->all(),
        ];
    }

    private function relatedDataService(): CvMakerRelatedDataService
    {
        if (!$this->relatedDataService) {
            $this->relatedDataService = app(CvMakerRelatedDataService::class);
        }

        return $this->relatedDataService;
    }

    private function organizationSyncService(): CvMakerOrganizationSyncService
    {
        if (!$this->organizationSyncService) {
            $this->organizationSyncService = app(CvMakerOrganizationSyncService::class);
        }

        return $this->organizationSyncService;
    }

    private function employeeBaseQuery(User $user): Builder
    {
        $query = Employee::query()
            ->select([
                'employees.nik',
                'employees.nama_karyawan',
                'employees.no_ktp',
                'employees.no_kk',
                'employees.tgl_lahir',
                'employees.jenis_kelamin',
                'employees.agama',
                'employees.golongan_darah',
                'employees.tinggi',
                'employees.berat',
                'employees.status_perkawinan',
                'employees.nama_ibu_kandung',
                'employees.nama_bapak',
                'employees.tanggal_menikah',
                'employees.no_telp',
                'employees.alamat_domisili',
                'employees.alamat_ktp',
                'employees.rt',
                'employees.rw',
                'employees.npwp',
                'employees.no_rekening',
                'employees.area_kerja',
                'employees.entry_date',
                'employees.departemen_id',
                'employees.divisi_id',
                'employees.provinsi_id',
                'employees.kabupaten_id',
                'employees.kecamatan_id',
                'employees.kelurahan_id',
                'employees.posisi',
                'employees.jabatan',
                'employees.job_title_id',
                'employees.organization_position_id',
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
                'jobTitle:id,job_level_id,name,name_zh',
                'jobTitle.level:id,code,name,rank',
                'organizationPosition:id,position_name,job_title_id,job_level_id',
                'organizationPosition.levelOverride:id,code,name,rank',
                'organizationPosition.jobTitle:id,job_level_id,name,name_zh',
                'organizationPosition.jobTitle.level:id,code,name,rank',
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

        $jobTitles = collect((array) $request->input('jabatan'))
            ->filter(fn($value) => is_scalar($value) && filled($value))
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();

        if ($jobTitles) {
            $query->whereIn('employees.nik', CvMakerProgressStatus::query()
                ->select('employee_nik')
                ->whereIn('cv_job_title', $jobTitles));
        }

        if ($request->filled('status_resign')) {
            $query->where('employees.status_resign', $request->input('status_resign'));
        }

        $progressStatus = trim((string) $request->input('cv_progress_status', ''));

        if ($progressStatus === 'not_synced') {
            $query->whereNotIn('employees.nik', CvMakerProgressStatus::query()->select('employee_nik'));
        } elseif ($progressStatus !== '') {
            $progressQuery = CvMakerProgressStatus::query()->select('employee_nik');

            if ($progressStatus === 'no_account') {
                $progressQuery->whereNull('cv_user_id');
            } elseif ($progressStatus === 'no_profile') {
                $progressQuery->whereNotNull('cv_user_id')->whereNull('cv_profile_id');
            } elseif ($progressStatus === 'in_progress') {
                $progressQuery->whereNotNull('cv_profile_id')->where('is_complete', false);
            } elseif ($progressStatus === 'complete') {
                $progressQuery->whereNotNull('cv_profile_id')->where('is_complete', true);
            } else {
                $progressQuery = null;
            }

            if ($progressQuery) {
                $query->whereIn('employees.nik', $progressQuery);
            }
        }

        $progressSteps = collect((array) $request->input('cv_progress_step'))
            ->filter(fn($value) => is_numeric($value) && (int) $value >= 1 && (int) $value <= 8)
            ->map(fn($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($progressSteps) {
            $query->whereIn('employees.nik', CvMakerProgressStatus::query()
                ->select('employee_nik')
                ->whereNotNull('cv_profile_id')
                ->whereIn('current_step', $progressSteps));
        }

        $reviewStatus = trim((string) $request->input('cv_review_status', ''));

        if (in_array($reviewStatus, array_keys(CvMakerProgressStatus::reviewLabels()), true)) {
            $query->whereIn('employees.nik', CvMakerProgressStatus::query()
                ->select('employee_nik')
                ->where('review_status', $reviewStatus));
        }

        if ($request->input('cv_reminder') === 'needs_reminder') {
            $query->whereIn('employees.nik', CvMakerProgressStatus::query()
                ->select('employee_nik')
                ->where('needs_reminder', true));
        }

        if ($request->input('cv_reminder') === 'not_needed') {
            $query->whereNotIn('employees.nik', CvMakerProgressStatus::query()
                ->select('employee_nik')
                ->where('needs_reminder', true));
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
            1 => 'employees.nik',
            2 => 'employees.nama_karyawan',
            3 => 'employees.status_resign',
        ];
        $columnIndex = (int) $request->input('order.0.column', 2);
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

    private function fetchProgressStatusesForEmployees(Collection $employees): array
    {
        $niks = $employees
            ->pluck('nik')
            ->filter()
            ->map(fn($nik) => (string) $nik)
            ->values()
            ->all();

        if (empty($niks)) {
            return [];
        }

        return CvMakerProgressStatus::query()
            ->whereIn('employee_nik', $niks)
            ->get()
            ->keyBy('employee_nik')
            ->all();
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

    private function buildVitaeView(?array $cvProfile): array
    {
        $empty = [
            'profile' => [],
            'educations' => [],
            'experiences' => [],
            'organizations' => [],
            'certifications' => [],
            'languages' => [],
            'projects' => [],
            'achievements' => [],
            'emergency_contacts' => [],
            'documents' => [],
            'has_content' => false,
        ];

        if (!$this->isConfigured() || !$cvProfile || empty($cvProfile['profile_id'])) {
            return $empty;
        }

        $profileId = (int) $cvProfile['profile_id'];
        $educations = $this->fetchCvRelatedRows($profileId, 'cv_educations', [
            'level',
            'institution',
            'major',
            'graduation_year',
            'sort_order',
        ]);
        $experiences = $this->fetchCvRelatedRows($profileId, 'cv_experiences', [
            'position',
            'company',
            'department',
            'division',
            'start_month',
            'end_month',
            'is_current',
            'responsibilities',
            'sort_order',
        ]);
        $organizations = $this->fetchCvRelatedRows($profileId, 'cv_organizations', [
            'organization_name',
            'role',
            'start_year',
            'end_year',
            'sort_order',
        ]);
        $certifications = $this->fetchCvRelatedRows($profileId, 'cv_certifications', [
            'name',
            'issuer',
            'year',
            'valid_until_year',
            'is_lifetime',
            'type',
            'sort_order',
        ]);
        $languages = $this->fetchCvRelatedRows($profileId, 'cv_languages', [
            'language',
            'level',
            'sort_order',
        ]);
        $projects = $this->fetchCvRelatedRows($profileId, 'cv_projects', [
            'name',
            'year',
            'sort_order',
        ]);
        $achievements = $this->fetchCvRelatedRows($profileId, 'cv_achievements', [
            'id', 'field', 'other_field', 'achievement_type', 'rank', 'level', 'other_level', 'period', 'sort_order', 'updated_at',
        ]);
        $emergencyContacts = $this->fetchCvRelatedRows($profileId, 'cv_emergency_contacts', [
            'id', 'phone', 'name', 'relationship', 'sort_order', 'updated_at',
        ]);
        $documents = $this->fetchCvRelatedRows($profileId, 'cv_documents', [
            'id', 'type', 'original_name', 'mime_type', 'file_size', 'uploaded_at', 'updated_at',
        ]);

        $vitae = [
            'profile' => [
                'name' => $cvProfile['full_name'] ?? null,
                'photo_available' => (bool) ($cvProfile['photo_available'] ?? false),
                'position' => $cvProfile['position'] ?? null,
                'summary' => $this->cleanLongText($cvProfile['profile_summary'] ?? null),
                'ktp_number' => $this->maskIdentityNumber($cvProfile['ktp_number'] ?? null),
                'family_card_number' => $this->maskIdentityNumber($cvProfile['family_card_number'] ?? null),
                'bank_account_number' => $this->maskIdentityNumber($cvProfile['bank_account_number'] ?? null),
                'npwp_number' => $this->maskIdentityNumber($cvProfile['npwp_number'] ?? null),
                'birth_place' => $this->plainDisplayValue($cvProfile['birth_place'] ?? null, 'text'),
                'birth_date' => $this->formatDate($cvProfile['birth_date'] ?? null),
                'birth' => $this->formatBirthInfo($cvProfile['birth_place'] ?? null, $cvProfile['birth_date'] ?? null),
                'gender' => $this->plainDisplayValue($cvProfile['gender'] ?? null, 'text'),
                'blood_type' => $this->plainDisplayValue($cvProfile['blood_type'] ?? null, 'blood_type'),
                'height' => $this->plainDisplayValue($cvProfile['height_cm'] ?? null, 'body_measurement'),
                'weight' => $this->plainDisplayValue($cvProfile['weight_kg'] ?? null, 'body_measurement'),
                'religion' => $this->plainDisplayValue($cvProfile['religion'] ?? null, 'religion'),
                'marital_status' => $this->plainDisplayValue($cvProfile['marital_status'] ?? null, 'text'),
                'marriage_date' => $this->formatDate($cvProfile['marriage_date'] ?? null),
                'spouse_name' => $this->plainDisplayValue($cvProfile['spouse_name'] ?? null, 'text'),
                'mother_name' => $this->plainDisplayValue($cvProfile['mother_name'] ?? null, 'text'),
                'has_children' => $this->booleanLabel($cvProfile['has_children'] ?? null),
                'children_names' => $this->splitCvList($cvProfile['children_names'] ?? null),
                'phone' => $this->plainDisplayValue($cvProfile['phone'] ?? null, 'text'),
                'email' => $this->plainDisplayValue($cvProfile['email'] ?? null, 'text'),
                'instagram' => $this->plainDisplayValue($cvProfile['instagram'] ?? null, 'text'),
                'linkedin' => $this->plainDisplayValue($cvProfile['linkedin'] ?? null, 'text'),
                'facebook' => $this->plainDisplayValue($cvProfile['facebook'] ?? null, 'text'),
                'ktp_address' => $this->cleanLongText($cvProfile['ktp_address'] ?? null),
                'rt' => $this->plainDisplayValue($cvProfile['rt'] ?? null, 'text'),
                'rw' => $this->plainDisplayValue($cvProfile['rw'] ?? null, 'text'),
                'domicile_same_as_ktp' => $this->booleanLabel($cvProfile['domicile_same_as_ktp'] ?? null),
                'address' => $this->cleanLongText($cvProfile['address'] ?? null),
                'province' => $cvProfile['province_name'] ?? null,
                'regency' => $cvProfile['regency_name'] ?? null,
                'district' => $cvProfile['district_name'] ?? null,
                'village' => $cvProfile['village_name'] ?? null,
                'location' => $this->joinNonEmpty([
                    $cvProfile['village_name'] ?? null,
                    $cvProfile['district_name'] ?? null,
                    $cvProfile['regency_name'] ?? null,
                    $cvProfile['province_name'] ?? null,
                ], ', '),
                'organization' => $this->joinNonEmpty([
                    $cvProfile['work_area'] ?? null,
                    $cvProfile['department'] ?? null,
                    $cvProfile['division'] ?? null,
                ], ' / '),
                'entry_date' => $this->formatDate($cvProfile['current_job_entry_date'] ?? null),
                'technical_skills' => $this->splitCvList($cvProfile['technical_skills'] ?? null),
                'non_technical_skills' => $this->splitCvList($cvProfile['non_technical_skills'] ?? null),
                'hobbies' => $this->interestItems($cvProfile['hobbies'] ?? null, $cvProfile['other_hobby'] ?? null),
                'talents' => $this->interestItems($cvProfile['talents'] ?? null, $cvProfile['other_talent'] ?? null),
                'last_generated_at' => $this->formatDateTime($cvProfile['last_generated_at'] ?? null),
                'updated_at' => $this->formatDateTime($cvProfile['updated_at'] ?? null),
            ],
            'educations' => $educations->map(function ($item) {
                return [
                    'title' => $this->joinNonEmpty([$item->level ?? null, $item->major ?? null], ' - '),
                    'institution' => $item->institution ?? null,
                    'year' => $item->graduation_year ?? null,
                ];
            })->all(),
            'experiences' => $experiences->map(function ($item) {
                return [
                    'title' => $item->position ?? null,
                    'company' => $item->company ?? null,
                    'department' => $item->department ?? null,
                    'division' => $item->division ?? null,
                    'period' => $this->formatMonthRange($item->start_month ?? null, $item->end_month ?? null, (bool) ($item->is_current ?? false)),
                    'responsibilities' => $this->splitCvList($item->responsibilities ?? null),
                ];
            })->all(),
            'organizations' => $organizations->map(function ($item) {
                return [
                    'title' => $item->organization_name ?? null,
                    'role' => $item->role ?? null,
                    'period' => $this->formatYearRange($item->start_year ?? null, $item->end_year ?? null),
                ];
            })->all(),
            'certifications' => $certifications->map(function ($item) {
                return [
                    'title' => $item->name ?? null,
                    'issuer' => $item->issuer ?? null,
                    'year' => $item->year ?? null,
                    'valid_until' => (bool) ($item->is_lifetime ?? false)
                        ? 'Seumur hidup'
                        : ($item->valid_until_year ?? null),
                    'type' => $item->type ?? null,
                    'period' => $this->formatCertificationPeriod(
                        $item->year ?? null,
                        $item->valid_until_year ?? null,
                        (bool) ($item->is_lifetime ?? false)
                    ),
                ];
            })->all(),
            'languages' => $languages->map(function ($item) {
                return [
                    'language' => $item->language ?? null,
                    'level' => $item->level ?? null,
                ];
            })->all(),
            'projects' => $projects->map(function ($item) {
                return [
                    'name' => $item->name ?? null,
                    'year' => $item->year ?? null,
                ];
            })->all(),
            'achievements' => $achievements->map(function ($item) {
                return [
                    'field' => ($item->field ?? null) === 'other' ? ($item->other_field ?? 'Lainnya') : ($item->field ?? null),
                    'type' => $item->achievement_type ?? null,
                    'rank' => $item->rank ?? null,
                    'level' => ($item->level ?? null) === 'other' ? ($item->other_level ?? 'Lainnya') : ($item->level ?? null),
                    'period' => $this->formatMonth($item->period ?? null),
                ];
            })->all(),
            'emergency_contacts' => $emergencyContacts->map(function ($item) {
                return [
                    'name' => $item->name ?? null,
                    'phone' => $item->phone ?? null,
                    'relationship' => $item->relationship ?? null,
                ];
            })->all(),
            'documents' => $documents->map(function ($item) {
                return [
                    'id' => (int) ($item->id ?? 0),
                    'type' => $item->type ?? null,
                    'label' => self::DOCUMENT_LABELS[$item->type ?? ''] ?? 'Dokumen',
                    'original_name' => $item->original_name ?? null,
                    'mime_type' => $item->mime_type ?? null,
                    'file_size' => $this->formatFileSize($item->file_size ?? null),
                    'uploaded_at' => $this->formatDateTime($item->uploaded_at ?? null),
                ];
            })->filter(fn($item) => $item['id'] > 0)->values()->all(),
        ];

        $vitae['has_content'] = filled($vitae['profile']['name'])
            || filled($vitae['profile']['summary'])
            || !empty($vitae['educations'])
            || !empty($vitae['experiences'])
            || !empty($vitae['organizations'])
            || !empty($vitae['certifications'])
            || !empty($vitae['languages'])
            || !empty($vitae['projects'])
            || !empty($vitae['achievements'])
            || !empty($vitae['emergency_contacts'])
            || !empty($vitae['documents']);

        return $vitae;
    }

    private function fetchCvRelatedRows(int $profileId, string $table, array $columns, int $limit = 50): Collection
    {
        if ($this->usesApi()) {
            $relatedKey = [
                'cv_educations' => 'educations',
                'cv_experiences' => 'experiences',
                'cv_organizations' => 'organizations',
                'cv_certifications' => 'certifications',
                'cv_languages' => 'languages',
                'cv_projects' => 'projects',
                'cv_achievements' => 'achievements',
                'cv_emergency_contacts' => 'emergency_contacts',
                'cv_documents' => 'documents',
            ][$table] ?? null;

            if (!$relatedKey) {
                return collect();
            }

            return collect($this->apiRelatedRowsByProfileId[$profileId][$relatedKey] ?? [])
                ->take($limit)
                ->map(function ($row) {
                    return (object) $row;
                });
        }

        try {
            $columns = $this->filterExistingCvColumns($table, $columns);

            if (empty($columns)) {
                return collect();
            }

            $query = DB::connection(config('services.cv_maker.connection', 'cv_maker'))
                ->table($table)
                ->where('cv_profile_id', $profileId)
                ->select($columns);

            if (in_array('sort_order', $columns, true)) {
                $query->orderBy('sort_order');
            }

            return $query
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (Throwable $exception) {
            Log::warning('CV Maker vitae lookup failed.', [
                'table' => $table,
                'profile_id' => $profileId,
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    private function cvProfileSelectColumns(): array
    {
        $availableColumns = $this->availableCvColumns('cv_profiles');
        $hasColumnInfo = !empty($availableColumns);
        $select = [
            !$hasColumnInfo || in_array('id', $availableColumns, true)
                ? 'cv_profiles.id as profile_id'
                : DB::raw('NULL as profile_id'),
        ];

        foreach (self::CV_PROFILE_COLUMNS as $column) {
            $select[] = !$hasColumnInfo || in_array($column, $availableColumns, true)
                ? 'cv_profiles.' . $column
                : DB::raw('NULL as ' . $column);
        }

        return $select;
    }

    private function filterExistingCvColumns(string $table, array $columns): array
    {
        $availableColumns = $this->availableCvColumns($table);

        if (empty($availableColumns)) {
            return $columns;
        }

        return collect($columns)
            ->filter(fn($column) => in_array($column, $availableColumns, true))
            ->values()
            ->all();
    }

    private function availableCvColumns(string $table): array
    {
        static $columnsByTable = [];

        $connection = config('services.cv_maker.connection', 'cv_maker');
        $cacheKey = $connection . '.' . $table;

        if (array_key_exists($cacheKey, $columnsByTable)) {
            return $columnsByTable[$cacheKey];
        }

        try {
            $columnsByTable[$cacheKey] = Schema::connection($connection)->getColumnListing($table);
        } catch (Throwable $exception) {
            Log::warning('CV Maker schema lookup failed.', [
                'table' => $table,
                'message' => $exception->getMessage(),
            ]);

            $columnsByTable[$cacheKey] = [];
        }

        return $columnsByTable[$cacheKey];
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

        if ($this->usesApi()) {
            return $this->fetchCvProfilesFromApi($hashToNik);
        }

        try {
            $profiles = DB::connection(config('services.cv_maker.connection', 'cv_maker'))
                ->table('users')
                ->leftJoin('cv_profiles', 'cv_profiles.user_id', '=', 'users.id')
                ->whereIn('users.vpeople_nik_hash', array_keys($hashToNik))
                ->select(array_merge([
                    'users.id as user_id',
                    'users.email as account_email',
                    'users.vpeople_nik_hash',
                    'users.vpeople_last_synced_at',
                ], $this->cvProfileSelectColumns()))
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
                    'birth_place' => $profile->birth_place,
                    'birth_date' => $profile->birth_date,
                    'ktp_number' => $profile->ktp_number,
                    'family_card_number' => $profile->family_card_number,
                    'bank_account_number' => $profile->bank_account_number,
                    'npwp_number' => $profile->npwp_number,
                    'photo_available' => filled($profile->photo_path ?? null),
                    'gender' => $profile->gender,
                    'height_cm' => $profile->height_cm,
                    'weight_kg' => $profile->weight_kg,
                    'blood_type' => $profile->blood_type,
                    'religion' => $profile->religion,
                    'marital_status' => $profile->marital_status,
                    'marriage_date' => $profile->marriage_date,
                    'spouse_name' => $profile->spouse_name,
                    'mother_name' => $profile->mother_name,
                    'ktp_address' => $profile->ktp_address,
                    'rt' => $profile->rt,
                    'rw' => $profile->rw,
                    'domicile_same_as_ktp' => $profile->domicile_same_as_ktp,
                    'has_children' => $profile->has_children,
                    'children_names' => $profile->children_names,
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
                    'instagram' => $profile->instagram,
                    'linkedin' => $profile->linkedin,
                    'facebook' => $profile->facebook,
                    'work_area' => $profile->work_area,
                    'department' => $profile->department,
                    'division' => $profile->division,
                    'job_title' => $profile->job_title ?? null,
                    'position' => $profile->position,
                    'job_title_id' => $profile->job_title_id ?? null,
                    'organization_position_id' => $profile->organization_position_id ?? null,
                    'job_level_code' => $profile->job_level_code ?? null,
                    'job_level_rank' => $profile->job_level_rank ?? null,
                    'organization_updated_at' => $profile->organization_updated_at ?? null,
                    'current_job_entry_date' => $profile->current_job_entry_date,
                    'profile_summary' => $profile->profile_summary,
                    'technical_skills' => $profile->technical_skills,
                    'non_technical_skills' => $profile->non_technical_skills,
                    'hobbies' => $profile->hobbies,
                    'other_hobby' => $profile->other_hobby,
                    'talents' => $profile->talents,
                    'other_talent' => $profile->other_talent,
                    'last_generated_at' => $profile->last_generated_at,
                    'updated_at' => $profile->updated_at,
                    'education_level' => $education['level'] ?? null,
                    'education_institution' => $education['institution'] ?? null,
                    'education_major' => $education['major'] ?? null,
                    'graduation_year' => $education['graduation_year'] ?? null,
                ]];
            })
            ->all();
    }

    private function fetchCvProfilesFromApi(array $hashToNik): array
    {
        $profiles = $this->apiClient()->profiles(array_keys($hashToNik));
        $result = [];

        foreach ($profiles as $hash => $profile) {
            $nik = $hashToNik[$hash] ?? null;

            if (!$nik) {
                continue;
            }

            $related = is_array($profile['related'] ?? null) ? $profile['related'] : [];
            $profileId = (int) ($profile['profile_id'] ?? 0);

            if ($profileId) {
                $this->apiRelatedRowsByProfileId[$profileId] = $related;
            }

            $education = collect($related['educations'] ?? [])
                ->map(function ($item) {
                    return (object) $item;
                })
                ->sortByDesc(function ($item) {
                    return ($this->educationRank($item->level ?? null) * 10000)
                        + ((int) ($item->graduation_year ?? 0));
                })
                ->first();

            unset($profile['related']);
            $profile['education_level'] = $education->level ?? null;
            $profile['education_institution'] = $education->institution ?? null;
            $profile['education_major'] = $education->major ?? null;
            $profile['graduation_year'] = $education->graduation_year ?? null;
            $result[$nik] = $profile;
        }

        return $result;
    }

    private function apiClient(): CvMakerApiClient
    {
        if (!$this->apiClient) {
            $this->apiClient = app(CvMakerApiClient::class);
        }

        return $this->apiClient;
    }

    private function usesApi(): bool
    {
        return $this->apiClient()->isConfigured()
            && in_array($this->transport(), ['api', 'auto'], true);
    }

    private function transport(): string
    {
        return strtolower(trim((string) config('services.cv_maker.transport', 'database')));
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

        if ($key === 'job_title') {
            // Update field CV Maker writes the legacy display column. The master
            // relation is compared separately through job_title_master/job_level.
            return $employee->jabatan ?: optional($employee->jobTitle)->display_name;
        }

        if ($key === 'position') {
            // CV Maker updates the legacy display column only. Keep the organization
            // assignment as a fallback because changing it must go through the
            // dedicated assignment workflow and its audit trail.
            return $employee->posisi ?: optional($employee->organizationPosition)->display_name;
        }

        if ($key === 'job_level_code') {
            return optional(optional($employee->organizationPosition)->effective_level)->code
                ?: optional(optional($employee->jobTitle)->level)->code;
        }

        return $employee->{$key};
    }

    private function cvComparableValue(?array $cvProfile, array $field)
    {
        if (!$cvProfile) {
            return null;
        }

        if (
            $field['key'] === 'job_level'
            && !empty($cvProfile['job_title_id'])
            && Schema::hasTable('job_titles')
            && Schema::hasTable('job_levels')
        ) {
            if ($this->jobLevelCodesByTitleId === null) {
                $this->jobLevelCodesByTitleId = JobTitle::query()
                    ->join('job_levels', 'job_titles.job_level_id', '=', 'job_levels.id')
                    ->pluck('job_levels.code', 'job_titles.id')
                    ->all();
            }

            return $this->jobLevelCodesByTitleId[(int) $cvProfile['job_title_id']]
                ?? ($cvProfile[$field['cv']] ?? null);
        }

        return $cvProfile[$field['cv']] ?? null;
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/[\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}]+/u', '', $value) ?: $value;
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim(mb_strtoupper($value));
    }

    private function normalizeReligion(string $value): ?string
    {
        $clean = $this->normalizeText($value);
        $raw = trim(mb_strtoupper($value));
        $search = $clean . ' ' . $raw;

        if (trim($search) === '') {
            return null;
        }

        if (strpos($search, 'ISLAM') !== false) {
            return 'ISLAM';
        }

        if (strpos($search, 'KATHOLIK') !== false || strpos($search, 'KATOLIK') !== false) {
            return 'KRISTEN KATHOLIK';
        }

        if (strpos($search, 'PROTESTAN') !== false || strpos($search, 'KRISTEN') !== false) {
            return 'KRISTEN PROTESTAN';
        }

        if (strpos($search, 'BUDDHA') !== false || strpos($search, 'BUDHA') !== false) {
            return 'BUDHA';
        }

        if (strpos($search, 'HINDU') !== false) {
            return 'HINDU';
        }

        if (strpos($search, 'KHONGHUCU') !== false || strpos($search, 'KONGHUCU') !== false) {
            return 'KHONGHUCU';
        }

        return $clean ?: null;
    }

    private function normalizeBloodType(string $value): ?string
    {
        $clean = mb_strtoupper(trim($value));
        $clean = preg_replace('/\s+/', '', $clean) ?: $clean;
        $clean = str_replace(
            ['GOLONGANDARAH', 'GOLONGAN', 'DARAH', 'BLOODTYPE', 'BLOOD', 'TYPE', 'TIPE'],
            '',
            $clean
        );
        $clean = str_replace(['POSITIF', 'POSITIVE', 'PLUS'], '+', $clean);
        $clean = str_replace(['NEGATIF', 'NEGATIVE', 'MINUS'], '-', $clean);
        $clean = str_replace('0', 'O', $clean);
        $clean = preg_replace('/[^ABO+\-]/', '', $clean) ?: '';

        return in_array($clean, ['A', 'B', 'AB', 'O', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)
            ? $clean
            : ($this->normalizeText($value) ?: null);
    }

    private function normalizeMeasurement(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));

        if ($value === '' || !preg_match('/\d+(?:\.\d+)?/', $value, $match)) {
            return null;
        }

        $number = $match[0];

        if (strpos($number, '.') !== false) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return $number !== '' ? $number : null;
    }

    private function maskIdentityNumber($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    private function booleanLabel($value): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return '-';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak';
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

        if ($type === 'identity_number') {
            return $this->normalizeForCompare($value, 'identity_number');
        }

        if (in_array($type, ['numeric_identifier', 'address_number'], true)) {
            $normalized = $this->normalizeForCompare($value, $type);
            $max = $field['max'] ?? 64;

            return $normalized && mb_strlen($normalized) <= $max ? $normalized : null;
        }

        if ($type === 'body_measurement') {
            $measurement = $this->normalizeForCompare($value, 'body_measurement');
            $max = $field['max'] ?? 4;

            return $measurement && mb_strlen($measurement) <= $max ? $measurement : null;
        }

        if ($type === 'blood_type') {
            $bloodType = $this->normalizeForCompare($value, 'blood_type');
            $max = $field['max'] ?? 8;

            return $bloodType && mb_strlen($bloodType) <= $max ? $bloodType : null;
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

        if ($type === 'religion') {
            $religion = trim(preg_replace('/\s+/', ' ', (string) $value) ?: (string) $value);
            $max = $field['max'] ?? 50;

            return $this->normalizeForCompare($religion, 'religion') && mb_strlen($religion) <= $max
                ? $religion
                : null;
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

        if (in_array($type, ['identity_number', 'numeric_identifier'], true)) {
            return $this->maskIdentityNumber($value);
        }

        if ($type === 'body_measurement' || $type === 'blood_type') {
            return $this->normalizeForCompare($value, $type) ?: (string) $value;
        }

        $decodedListText = $this->decodedCvListToText($value);

        if ($decodedListText !== null) {
            return $decodedListText;
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

        if (in_array($type, ['identity_number', 'numeric_identifier'], true)) {
            return e($this->maskIdentityNumber($value));
        }

        if ($type === 'body_measurement' || $type === 'blood_type') {
            return e($this->normalizeForCompare($value, $type) ?: (string) $value);
        }

        $decodedListText = $this->decodedCvListToText($value);

        if ($decodedListText !== null) {
            return e($decodedListText);
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

    private function cleanLongText($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = (string) $value;
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?: $text;
        $text = preg_replace('/<\/\s*p\s*>/i', "\n", $text) ?: $text;
        $text = trim(strip_tags($text));
        $text = preg_replace("/[ \t]+/", ' ', $text) ?: $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?: $text;

        return trim($text) !== '' ? trim($text) : null;
    }

    private function splitCvList($value): array
    {
        $decodedItems = $this->decodeCvList($value);

        if ($decodedItems !== null) {
            return collect($decodedItems)
                ->flatMap(fn($item) => is_array($item) ? array_values($item) : [$item])
                ->map(fn($item) => is_scalar($item) ? $this->cleanLongText($item) : null)
                ->filter(fn($item) => filled($item))
                ->unique()
                ->take(30)
                ->values()
                ->all();
        }

        $text = $this->cleanLongText($value);

        if (!$text) {
            return [];
        }

        $text = trim($text, "[] \t\n\r\0\x0B");
        $items = preg_split('/\r\n|\r|\n|;/', $text) ?: [];

        return collect($items)
            ->map(fn($item) => trim((string) $item, " \t\n\r\0\x0B\"';"))
            ->filter(fn($item) => $item !== '')
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    private function interestItems($value, $other): array
    {
        $items = $this->decodeCvList($value);
        $result = collect(is_array($items) ? $items : [])
            ->flatMap(function ($item, $key) {
                if (is_array($item)) {
                    return array_values($item);
                }

                return is_string($key) && filled($item) ? [$item] : [$item];
            })
            ->map(fn($item) => is_scalar($item) ? $this->cleanLongText($item) : null)
            ->filter(fn($item) => filled($item));

        if (filled($other)) {
            $result->push($this->cleanLongText($other));
        }

        return $result->filter()->unique()->values()->all();
    }

    private function formatFileSize($bytes): string
    {
        $bytes = is_numeric($bytes) ? (int) $bytes : 0;

        if ($bytes < 1) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }

        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    private function decodeCvList($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = trim((string) $value);

        if (!in_array(substr($text, 0, 1), ['[', '{'], true)) {
            return null;
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return null;
    }

    private function decodedCvListToText($value): ?string
    {
        $decodedItems = $this->decodeCvList($value);

        if ($decodedItems === null) {
            return null;
        }

        $items = collect($decodedItems)
            ->flatMap(fn($item) => is_array($item) ? array_values($item) : [$item])
            ->map(fn($item) => is_scalar($item) ? $this->cleanLongText($item) : null)
            ->filter(fn($item) => filled($item))
            ->unique()
            ->values();

        return $items->isNotEmpty() ? $items->join(', ') : '-';
    }

    private function joinNonEmpty(array $values, string $separator): ?string
    {
        $items = collect($values)
            ->map(fn($value) => $value === null ? null : trim((string) $value))
            ->filter(fn($value) => filled($value))
            ->values()
            ->all();

        return $items ? implode($separator, $items) : null;
    }

    private function formatBirthInfo($place, $date): ?string
    {
        return $this->joinNonEmpty([
            $place,
            $this->formatDate($date),
        ], ', ');
    }

    private function formatDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (Throwable $exception) {
            return (string) $value;
        }
    }

    private function formatDateTime($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (Throwable $exception) {
            return (string) $value;
        }
    }

    private function formatMonthRange($start, $end, bool $isCurrent): ?string
    {
        $startLabel = $this->formatMonth($start);
        $endLabel = $isCurrent ? 'Sekarang' : $this->formatMonth($end);

        return $this->formatRange($startLabel, $endLabel);
    }

    private function formatMonth($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        try {
            return Carbon::parse(strlen($value) === 7 ? $value . '-01' : $value)->format('m/Y');
        } catch (Throwable $exception) {
            return $value;
        }
    }

    private function formatYearRange($start, $end): ?string
    {
        return $this->formatRange($start, $end);
    }

    private function formatCertificationPeriod($year, $validUntilYear, bool $isLifetime): ?string
    {
        if ($isLifetime) {
            return $this->formatRange($year, 'Seumur hidup');
        }

        return $this->formatRange($year, $validUntilYear);
    }

    private function formatRange($start, $end): ?string
    {
        $start = $start === null ? '' : trim((string) $start);
        $end = $end === null ? '' : trim((string) $end);

        if ($start !== '' && $end !== '') {
            return $start . ' - ' . $end;
        }

        return $start !== '' ? $start : ($end !== '' ? $end : null);
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

    public function renderProgressSnapshot(?CvMakerProgressStatus $progressStatus): string
    {
        if (!$progressStatus) {
            return '<div class="cv-progress-snapshot cv-progress-snapshot--empty">'
                . '<span class="badge bg-light text-dark border">Snapshot belum tersedia</span>'
                . '<div class="cv-status-meta">Menunggu sinkronisasi terjadwal.</div>'
                . '</div>';
        }

        if (empty($progressStatus->cv_profile_id)) {
            return '<div class="cv-progress-snapshot cv-progress-snapshot--empty">'
                . '<span class="badge bg-light text-dark border">Profil CV belum ditemukan</span>'
                . '<div class="cv-status-meta">Sync terakhir: '
                . e($progressStatus->last_synced_at ? $progressStatus->last_synced_at->format('d/m/Y H:i') : '-')
                . '</div>'
                . '</div>';
        }

        $badges = [];

        if ($progressStatus->is_complete) {
            $badges[] = '<span class="badge bg-success">Tahap 8 selesai</span>';
        } elseif ($progressStatus->needs_reminder) {
            $badges[] = '<span class="badge bg-warning text-dark">Perlu Diingatkan</span>';
        } else {
            $badges[] = '<span class="badge bg-light text-dark border">Dalam Progress</span>';
        }

        $reviewStatus = $progressStatus->review_status ?: CvMakerProgressStatus::REVIEW_UNREVIEWED;
        $reviewLabels = CvMakerProgressStatus::reviewLabels();
        $reviewClasses = [
            CvMakerProgressStatus::REVIEW_UNREVIEWED => 'bg-secondary',
            CvMakerProgressStatus::REVIEW_IN_PROGRESS => 'bg-info text-dark',
            CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION => 'bg-warning text-dark',
            CvMakerProgressStatus::REVIEW_COMPLETED => 'bg-success',
        ];
        $badges[] = '<span class="badge ' . ($reviewClasses[$reviewStatus] ?? 'bg-secondary') . '">'
            . e($reviewLabels[$reviewStatus] ?? $reviewStatus) . '</span>';

        $stepLabel = 'Tahap ' . (int) $progressStatus->current_step
            . '/' . (int) ($progressStatus->total_step_count ?: CvMakerProgressSnapshotService::TOTAL_STEPS)
            . ' - ' . e($progressStatus->current_step_label ?: '-');
        $lastActivity = $progressStatus->last_activity_at
            ? 'Aktivitas terakhir: ' . e($progressStatus->last_activity_at->format('d/m/Y H:i'))
            : 'Aktivitas terakhir: -';

        return '<div class="cv-progress-snapshot">'
            . '<div class="cv-progress-snapshot__badges">' . implode('', $badges) . '</div>'
            . '<div class="cv-status-meta">' . $stepLabel . '</div>'
            . '<div class="cv-status-meta">' . $lastActivity . '</div>'
            . '</div>';
    }

    private function renderCvStatus(?array $cvProfile, ?CvMakerProgressStatus $progressStatus = null): string
    {
        if (!$this->isConfigured()) {
            return '<span class="badge bg-warning text-dark">Belum dikonfigurasi</span>'
                . $this->renderProgressSnapshot($progressStatus);
        }

        if (!$cvProfile) {
            return '<span class="badge bg-secondary">Tidak ada akun CV</span>'
                . $this->renderProgressSnapshot($progressStatus);
        }

        if (empty($cvProfile['profile_id'])) {
            return '<span class="badge bg-secondary">Profil kosong</span>'
                . $this->renderProgressSnapshot($progressStatus);
        }

        $status = $cvProfile['status'] ?: 'draft';
        $badgeClass = $status === 'generated'
            ? 'bg-success'
            : ($status === 'submitted' ? 'bg-info text-dark' : 'bg-light text-dark border');
        $updatedAt = $cvProfile['updated_at']
            ? '<div class="cv-status-meta">' . e(Carbon::parse($cvProfile['updated_at'])->format('d/m/Y H:i')) . '</div>'
            : '';

        return '<span class="badge ' . $badgeClass . '">' . e(ucfirst($status)) . '</span>'
            . $updatedAt
            . $this->renderProgressSnapshot($progressStatus);
    }

    private function renderResultCell(Employee $employee, array $comparison, ?array $cvProfile): string
    {
        $detailUrl = route('cv-maker-compare.show', $employee->nik);

        return '<div class="cv-result-cell">'
            . '<div class="cv-result-cell__summary">' . $this->renderMismatchSummary($comparison, $cvProfile) . '</div>'
            . '<a href="' . e($detailUrl) . '" class="btn btn-sm btn-outline-primary ui-btn-icon cv-result-cell__detail">'
            . '<i class="fas fa-eye"></i>'
            . '<span>Detail</span>'
            . '</a>'
            . '</div>';
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

}
