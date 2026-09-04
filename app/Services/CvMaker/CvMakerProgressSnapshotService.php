<?php

namespace App\Services\CvMaker;

use App\Models\CvMakerProgressHistory;
use App\Models\CvMakerProgressStatus;
use App\Models\CvMakerPositionSkillCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CvMakerProgressSnapshotService
{
    private $apiClient;

    public function __construct(CvMakerApiClient $apiClient = null)
    {
        $this->apiClient = $apiClient;
    }

    public const TOTAL_STEPS = 8;
    public const REMINDER_REASON_IDLE = 'Draft CV belum lengkap dan tidak ada aktivitas lebih dari 24 jam.';

    private const STEPS = [
        1 => ['key' => 'personal', 'label' => 'Data Pribadi'],
        2 => ['key' => 'summary', 'label' => 'Ringkasan Profil'],
        3 => ['key' => 'education', 'label' => 'Pendidikan'],
        4 => ['key' => 'experience', 'label' => 'Pengalaman'],
        5 => ['key' => 'skills', 'label' => 'Keahlian'],
        6 => ['key' => 'certifications', 'label' => 'Sertifikasi'],
        7 => ['key' => 'extras', 'label' => 'Tambahan'],
        8 => ['key' => 'documents', 'label' => 'Dokumen'],
    ];

    private const REQUIRED_DOCUMENT_TYPES = [
        'ktp',
        'family_card',
        'diploma',
    ];

    public function evaluateProgress(array $profile, array $relatedRows, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $stepResults = [
            'personal' => $this->personalStepComplete($profile),
            'summary' => $this->filled($profile['profile_summary'] ?? null),
            'education' => $this->educationStepComplete($relatedRows['educations'] ?? []),
            'experience' => $this->experienceStepComplete($relatedRows['experiences'] ?? []),
            'skills' => $this->listHasItems($profile['technical_skills'] ?? null),
            'certifications' => $this->optionalRowsComplete($relatedRows['certifications'] ?? [], [
                'name',
                'issuer',
                'year',
            ]),
            'extras' => $this->extrasStepComplete($relatedRows),
            'documents' => $this->documentsStepComplete($relatedRows['documents'] ?? []),
        ];

        $completedStepKeys = [];
        $missingSteps = [];

        foreach (self::STEPS as $step) {
            if (!empty($stepResults[$step['key']])) {
                $completedStepKeys[] = $step['key'];
            } else {
                $missingSteps[] = $step['key'];
            }
        }

        $isComplete = empty($missingSteps);
        $currentStepNumber = self::TOTAL_STEPS;

        if (!$isComplete) {
            foreach (self::STEPS as $number => $step) {
                if (in_array($step['key'], $missingSteps, true)) {
                    $currentStepNumber = $number;
                    break;
                }
            }
        }

        $currentStep = self::STEPS[$currentStepNumber];
        $completedSteps = $isComplete
            ? $completedStepKeys
            : $this->completedStepsBefore($currentStepNumber);
        $lastActivityAt = $this->lastActivityAt($profile, $relatedRows);
        $needsReminder = $this->needsReminder($profile, $isComplete, $lastActivityAt, $now);

        return [
            'current_step' => $currentStepNumber,
            'current_step_key' => $currentStep['key'],
            'current_step_label' => $currentStep['label'],
            'completed_step_count' => count($completedSteps),
            'total_step_count' => self::TOTAL_STEPS,
            'is_complete' => $isComplete,
            'needs_reminder' => $needsReminder,
            'reminder_reason' => $needsReminder ? self::REMINDER_REASON_IDLE : null,
            'last_activity_at' => $lastActivityAt,
            'completed_steps' => $completedSteps,
            'missing_steps' => $missingSteps,
        ];
    }

    public function syncEmployeeProgress(
        string $employeeNik,
        ?array $profile,
        array $relatedRows = [],
        ?Carbon $now = null,
        bool $dryRun = false,
        bool $recordInitialHistory = true
    ): array {
        $now = $now ?: Carbon::now();
        $profile = $profile ?: [];
        $progress = $this->evaluateProgress($profile, $relatedRows, $now);
        $payload = $this->statusPayload($employeeNik, $profile, $progress, $now);

        if ($dryRun) {
            return [
                'written' => false,
                'history_created' => 0,
                'progress' => $progress,
            ];
        }

        $historyCreated = 0;

        DB::transaction(function () use ($employeeNik, $payload, $progress, $recordInitialHistory, &$historyCreated) {
            $existing = CvMakerProgressStatus::query()
                ->where('employee_nik', $employeeNik)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $historyEvents = $this->historyEventsForChange($existing, $payload, $progress);
                $previousActivity = $existing->last_activity_at;
                $newActivity = $payload['last_activity_at'];
                $hasNewActivity = $newActivity && (
                    !$previousActivity || Carbon::parse($newActivity)->gt($previousActivity)
                );

                if ($hasNewActivity && Schema::hasColumn('cv_maker_progress_statuses', 'review_status')) {
                    $existing->forceFill([
                        'review_status' => CvMakerProgressStatus::REVIEW_UNREVIEWED,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'review_note' => null,
                    ]);
                }
                $existing->fill($payload)->save();
                $status = $existing;
            } else {
                $status = CvMakerProgressStatus::create($payload);
                $historyEvents = $recordInitialHistory
                    ? [[
                        'event_type' => CvMakerProgressHistory::EVENT_SNAPSHOT_CREATED,
                        'from_step' => null,
                        'to_step' => $payload['current_step'],
                        'from_needs_reminder' => null,
                        'to_needs_reminder' => $payload['needs_reminder'],
                        'message' => 'Snapshot progress CV Maker dibuat pada tahap ' . $payload['current_step'] . '.',
                    ]]
                    : [];
            }

            foreach ($historyEvents as $event) {
                CvMakerProgressHistory::create(array_merge($event, [
                    'cv_maker_progress_status_id' => $status->id,
                    'employee_nik' => $employeeNik,
                    'cv_status' => $payload['cv_status'],
                    'last_activity_at' => $payload['last_activity_at'],
                    'metadata' => [
                        'current_step_key' => $payload['current_step_key'],
                        'current_step_label' => $payload['current_step_label'],
                        'completed_step_count' => $payload['completed_step_count'],
                        'total_step_count' => $payload['total_step_count'],
                        'is_complete' => $payload['is_complete'],
                        'missing_steps' => $progress['missing_steps'],
                    ],
                ]));

                $historyCreated++;
            }
        });

        return [
            'written' => true,
            'history_created' => $historyCreated,
            'progress' => $progress,
        ];
    }

    public function syncActiveEmployees(
        int $limit = 500,
        int $chunk = 100,
        bool $dryRun = false,
        ?Carbon $now = null
    ): array {
        $now = $now ?: Carbon::now();
        $limit = max(1, min($limit, 20000));
        $chunk = max(1, min($chunk, 1000));

        $summary = [
            'configured' => $this->isConfigured(),
            'checked' => 0,
            'synced' => 0,
            'skipped_no_profile' => 0,
            'history_created' => 0,
            'dry_run' => $dryRun,
        ];

        if (!$summary['configured']) {
            return $summary;
        }

        $niks = DB::table('employees')
            ->leftJoin('cv_maker_progress_statuses', 'cv_maker_progress_statuses.employee_nik', '=', 'employees.nik')
            ->where('employees.status_resign', 'AKTIF')
            ->orderBy('cv_maker_progress_statuses.last_synced_at')
            ->orderBy('employees.nik')
            ->limit($limit)
            ->pluck('employees.nik')
            ->map(function ($nik) {
                return (string) $nik;
            })
            ->values()
            ->all();

        $summary['checked'] = count($niks);

        foreach (array_chunk($niks, $chunk) as $nikChunk) {
            $payloads = $this->fetchCvMakerPayloadsForNiks($nikChunk);

            foreach ($nikChunk as $nik) {
                if (!isset($payloads[$nik])) {
                    $this->syncEmployeeProgress($nik, null, [], $now, $dryRun, false);
                    $summary['skipped_no_profile']++;
                    continue;
                }

                $result = $this->syncEmployeeProgress(
                    $nik,
                    $payloads[$nik]['profile'],
                    $payloads[$nik]['related'],
                    $now,
                    $dryRun
                );

                $summary['synced']++;
                $summary['history_created'] += (int) ($result['history_created'] ?? 0);
            }
        }

        return $summary;
    }

    public function isConfigured(): bool
    {
        $transport = $this->transport();
        $connection = config('services.cv_maker.connection', 'cv_maker');
        $apiConfigured = $this->apiClient()->isConfigured();
        $databaseConfigured = filled(config('services.cv_maker.nik_hash_key'))
            && (
                filled(config('database.connections.' . $connection . '.database'))
                || filled(config('database.connections.' . $connection . '.url'))
            );

        if ($transport === 'api') {
            return $apiConfigured;
        }

        return $transport === 'auto'
            ? ($apiConfigured || $databaseConfigured)
            : $databaseConfigured;
    }

    public function hashNik(string $nik): string
    {
        return hash_hmac('sha256', $nik, (string) config('services.cv_maker.nik_hash_key'));
    }

    public static function stepDefinitions(): array
    {
        return self::STEPS;
    }

    private function statusPayload(string $employeeNik, array $profile, array $progress, Carbon $now): array
    {
        return [
            'employee_nik' => $employeeNik,
            'cv_user_id' => $this->nullableInteger($profile['user_id'] ?? null),
            'cv_profile_id' => $this->nullableInteger($profile['profile_id'] ?? null),
            'cv_status' => $this->nullableString($profile['status'] ?? null),
            'cv_job_title' => $this->nullableString($profile['job_title'] ?? null),
            'cv_position' => $this->nullableString($profile['position'] ?? null),
            'cv_position_normalized' => CvMakerPositionSkillCategory::normalizePosition($profile['position'] ?? null),
            'current_step' => $progress['current_step'],
            'current_step_key' => $progress['current_step_key'],
            'current_step_label' => $progress['current_step_label'],
            'completed_step_count' => $progress['completed_step_count'],
            'total_step_count' => $progress['total_step_count'],
            'is_complete' => $progress['is_complete'],
            'needs_reminder' => $progress['needs_reminder'],
            'reminder_reason' => $progress['reminder_reason'],
            'last_activity_at' => $progress['last_activity_at'],
            'last_synced_at' => $now,
            'completed_steps' => $progress['completed_steps'],
            'missing_steps' => $progress['missing_steps'],
            'metadata' => [
                'profile_found' => !empty($profile['profile_id']),
            ],
        ];
    }

    private function historyEventsForChange(CvMakerProgressStatus $existing, array $payload, array $progress): array
    {
        $events = [];
        $fromStep = (int) $existing->current_step;
        $toStep = (int) $payload['current_step'];
        $fromReminder = (bool) $existing->needs_reminder;
        $toReminder = (bool) $payload['needs_reminder'];

        if (empty($existing->cv_profile_id) && !empty($payload['cv_profile_id'])) {
            return [[
                'event_type' => CvMakerProgressHistory::EVENT_SNAPSHOT_CREATED,
                'from_step' => null,
                'to_step' => $toStep,
                'from_needs_reminder' => null,
                'to_needs_reminder' => $toReminder,
                'message' => 'Profil CV Maker ditemukan dan snapshot progress dibuat pada tahap ' . $toStep . '.',
            ]];
        }

        if ($fromStep !== $toStep || (bool) $existing->is_complete !== (bool) $payload['is_complete']) {
            $events[] = [
                'event_type' => CvMakerProgressHistory::EVENT_PROGRESS_CHANGED,
                'from_step' => $fromStep,
                'to_step' => $toStep,
                'from_needs_reminder' => $fromReminder,
                'to_needs_reminder' => $toReminder,
                'message' => 'Progress CV Maker berubah dari tahap ' . $fromStep . ' ke tahap ' . $toStep . '.',
            ];
        }

        if ($fromReminder !== $toReminder) {
            $events[] = [
                'event_type' => $toReminder
                    ? CvMakerProgressHistory::EVENT_REMINDER_NEEDED
                    : CvMakerProgressHistory::EVENT_REMINDER_CLEARED,
                'from_step' => $fromStep,
                'to_step' => $toStep,
                'from_needs_reminder' => $fromReminder,
                'to_needs_reminder' => $toReminder,
                'message' => $toReminder
                    ? self::REMINDER_REASON_IDLE
                    : 'Reminder CV Maker dibersihkan karena progress berubah atau aktivitas baru terdeteksi.',
            ];
        }

        return $events;
    }

    private function fetchCvMakerPayloadsForNiks(array $niks): array
    {
        if (empty($niks)) {
            return [];
        }

        $connection = config('services.cv_maker.connection', 'cv_maker');
        $hashToNik = [];

        foreach ($niks as $nik) {
            $hashToNik[$this->hashNik((string) $nik)] = (string) $nik;
        }

        if ($this->usesApi()) {
            return $this->fetchCvMakerPayloadsFromApi($hashToNik);
        }

        try {
            $profiles = DB::connection($connection)
                ->table('users')
                ->leftJoin('cv_profiles', 'cv_profiles.user_id', '=', 'users.id')
                ->whereIn('users.vpeople_nik_hash', array_keys($hashToNik))
                ->select([
                    'users.id as user_id',
                    'users.vpeople_nik_hash',
                    'cv_profiles.id as profile_id',
                    'cv_profiles.status',
                    'cv_profiles.job_title',
                    'cv_profiles.position',
                    'cv_profiles.full_name',
                    'cv_profiles.birth_date',
                    'cv_profiles.birth_place',
                    'cv_profiles.gender',
                    'cv_profiles.marital_status',
                    'cv_profiles.address',
                    'cv_profiles.phone',
                    'cv_profiles.email',
                    'cv_profiles.profile_summary',
                    'cv_profiles.technical_skills',
                    'cv_profiles.updated_at',
                ])
                ->get();
        } catch (Throwable $exception) {
            Log::warning('CV Maker progress profile lookup failed.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $profileIds = $profiles
            ->pluck('profile_id')
            ->filter()
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
        $relatedRows = $this->fetchRelatedRowsByProfileIds($connection, $profileIds);
        $payloads = [];

        foreach ($profiles as $profile) {
            $nik = $hashToNik[$profile->vpeople_nik_hash] ?? null;

            if (!$nik || empty($profile->profile_id)) {
                continue;
            }

            $payloads[$nik] = [
                'profile' => (array) $profile,
                'related' => $relatedRows[(int) $profile->profile_id] ?? $this->emptyRelatedRows(),
            ];
        }

        return $payloads;
    }

    private function fetchCvMakerPayloadsFromApi(array $hashToNik): array
    {
        $profiles = $this->apiClient()->profiles(array_keys($hashToNik));
        $payloads = [];

        foreach ($profiles as $hash => $profile) {
            $nik = $hashToNik[$hash] ?? null;

            if (!$nik || empty($profile['profile_id'])) {
                continue;
            }

            $related = is_array($profile['related'] ?? null)
                ? $profile['related']
                : $this->emptyRelatedRows();
            unset($profile['related']);

            $payloads[$nik] = [
                'profile' => $profile,
                'related' => array_merge($this->emptyRelatedRows(), $related),
            ];
        }

        return $payloads;
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

    private function fetchRelatedRowsByProfileIds(string $connection, array $profileIds): array
    {
        $result = [];

        foreach ($profileIds as $profileId) {
            $result[(int) $profileId] = $this->emptyRelatedRows();
        }

        if (empty($profileIds)) {
            return $result;
        }

        $tables = [
            'educations' => ['table' => 'cv_educations', 'columns' => ['cv_profile_id', 'level', 'institution', 'major', 'graduation_year', 'updated_at']],
            'experiences' => ['table' => 'cv_experiences', 'columns' => ['cv_profile_id', 'position', 'company', 'department', 'division', 'start_month', 'end_month', 'is_current', 'responsibilities', 'updated_at']],
            'certifications' => ['table' => 'cv_certifications', 'columns' => ['cv_profile_id', 'name', 'issuer', 'year', 'valid_until_year', 'is_lifetime', 'updated_at']],
            'languages' => ['table' => 'cv_languages', 'columns' => ['cv_profile_id', 'language', 'level', 'updated_at']],
            'projects' => ['table' => 'cv_projects', 'columns' => ['cv_profile_id', 'name', 'year', 'updated_at']],
            'organizations' => ['table' => 'cv_organizations', 'columns' => ['cv_profile_id', 'organization_name', 'role', 'start_year', 'end_year', 'updated_at']],
            'emergency_contacts' => ['table' => 'cv_emergency_contacts', 'columns' => ['cv_profile_id', 'phone', 'name', 'relationship', 'updated_at']],
            'documents' => ['table' => 'cv_documents', 'columns' => ['cv_profile_id', 'type', 'uploaded_at', 'updated_at']],
        ];

        foreach ($tables as $key => $config) {
            foreach ($this->fetchRowsByProfileIds($connection, $config['table'], $config['columns'], $profileIds) as $row) {
                $profileId = (int) ($row['cv_profile_id'] ?? 0);

                if ($profileId && isset($result[$profileId])) {
                    $result[$profileId][$key][] = $row;
                }
            }
        }

        return $result;
    }

    private function fetchRowsByProfileIds(string $connection, string $table, array $columns, array $profileIds): array
    {
        try {
            return DB::connection($connection)
                ->table($table)
                ->whereIn('cv_profile_id', $profileIds)
                ->select($columns)
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all();
        } catch (Throwable $exception) {
            Log::warning('CV Maker progress related lookup failed.', [
                'table' => $table,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function emptyRelatedRows(): array
    {
        return [
            'educations' => [],
            'experiences' => [],
            'certifications' => [],
            'languages' => [],
            'projects' => [],
            'organizations' => [],
            'emergency_contacts' => [],
            'documents' => [],
        ];
    }

    private function personalStepComplete(array $profile): bool
    {
        foreach ([
            'full_name',
            'birth_date',
            'birth_place',
            'gender',
            'marital_status',
            'address',
            'phone',
            'email',
        ] as $field) {
            if (!$this->filled($profile[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function completedStepsBefore(int $currentStepNumber): array
    {
        $completed = [];

        foreach (self::STEPS as $number => $step) {
            if ($number >= $currentStepNumber) {
                break;
            }

            $completed[] = $step['key'];
        }

        return $completed;
    }

    private function educationStepComplete(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($this->educationRowComplete((array) $row)) {
                return true;
            }
        }

        return false;
    }

    private function educationRowComplete(array $row): bool
    {
        if (!$this->filled($row['level'] ?? null)
            || !$this->filled($row['institution'] ?? null)
            || !$this->filled($row['graduation_year'] ?? null)) {
            return false;
        }

        if ($this->educationLevelAllowsEmptyMajor($row['level'] ?? null)) {
            return true;
        }

        return $this->filled($row['major'] ?? null);
    }

    private function educationLevelAllowsEmptyMajor($level): bool
    {
        $level = strtoupper(trim((string) $level));

        return strpos($level, 'SD') === 0 || strpos($level, 'SMP') === 0;
    }

    private function experienceStepComplete(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($this->experienceRowComplete((array) $row)) {
                return true;
            }
        }

        return false;
    }

    private function experienceRowComplete(array $row): bool
    {
        foreach ([
            'position',
            'company',
            'department',
            'division',
            'start_month',
            'responsibilities',
        ] as $field) {
            if (!$this->filled($row[$field] ?? null)) {
                return false;
            }
        }

        if ($this->truthy($row['is_current'] ?? false)) {
            return true;
        }

        return $this->filled($row['end_month'] ?? null);
    }

    private function extrasStepComplete(array $relatedRows): bool
    {
        return $this->optionalRowsComplete($relatedRows['languages'] ?? [], ['language', 'level'])
            && $this->optionalRowsComplete($relatedRows['projects'] ?? [], ['name', 'year'])
            && $this->optionalRowsComplete($relatedRows['organizations'] ?? [], [
                'organization_name',
                'role',
                'start_year',
                'end_year',
            ]);
    }

    private function optionalRowsComplete(array $rows, array $requiredFields): bool
    {
        foreach ($rows as $row) {
            $row = (array) $row;

            if (!$this->rowHasAnyValue($row, $requiredFields)) {
                continue;
            }

            foreach ($requiredFields as $field) {
                if (!$this->filled($row[$field] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function documentsStepComplete(array $rows): bool
    {
        $availableTypes = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $type = trim((string) ($row['type'] ?? ''));

            if ($type !== '') {
                $availableTypes[$type] = true;
            }
        }

        foreach (self::REQUIRED_DOCUMENT_TYPES as $type) {
            if (empty($availableTypes[$type])) {
                return false;
            }
        }

        return true;
    }

    private function lastActivityAt(array $profile, array $relatedRows): ?Carbon
    {
        $latest = $this->parseDate($profile['updated_at'] ?? null);

        foreach ($relatedRows as $rows) {
            foreach ((array) $rows as $row) {
                $row = (array) $row;

                foreach (['updated_at', 'uploaded_at'] as $field) {
                    $date = $this->parseDate($row[$field] ?? null);

                    if ($date && (!$latest || $date->greaterThan($latest))) {
                        $latest = $date;
                    }
                }
            }
        }

        return $latest;
    }

    private function needsReminder(array $profile, bool $isComplete, ?Carbon $lastActivityAt, Carbon $now): bool
    {
        if ($isComplete || !$lastActivityAt) {
            return false;
        }

        $status = strtolower(trim((string) ($profile['status'] ?? '')));

        if ($status !== 'draft') {
            return false;
        }

        return $lastActivityAt->lessThanOrEqualTo($now->copy()->subHours(24));
    }

    private function rowHasAnyValue(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($this->filled($row[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function filled($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            return count(array_filter($value, function ($item) {
                return $this->filled($item);
            })) > 0;
        }

        return trim((string) $value) !== '';
    }

    private function listHasItems($value): bool
    {
        if (is_array($value)) {
            return $this->filled($value);
        }

        if (!$this->filled($value)) {
            return false;
        }

        $text = trim((string) $value);

        if (in_array(substr($text, 0, 1), ['[', '{'], true)) {
            $decoded = json_decode($text, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->filled($decoded);
            }
        }

        $items = preg_split('/[,;\n]+/', $text) ?: [];

        return count(array_filter(array_map('trim', $items))) > 0;
    }

    private function truthy($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function parseDate($value): ?Carbon
    {
        if (!$this->filled($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function nullableInteger($value): ?int
    {
        if (!$this->filled($value) || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableString($value): ?string
    {
        if (!$this->filled($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
