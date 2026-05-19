<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Vhire\VhireOnboardingContractService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkGenerateVhireNikActivation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 900;

    private array $contractIds;
    private ?string $actorUserId;

    public function __construct(array $contractIds, ?string $actorUserId = null)
    {
        $this->contractIds = collect($contractIds)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->actorUserId = $actorUserId;
    }

    public function handle(VhireOnboardingContractService $service): void
    {
        if (empty($this->contractIds)) {
            return;
        }

        $actor = $this->actorUserId ? User::query()->find($this->actorUserId) : null;
        $request = Request::create('/jobs/vhire/bulk-generate-nik-activation', 'POST');
        $request->headers->set('User-Agent', 'HRIS queued V-Hire bulk NIK activation');
        $request->setUserResolver(fn() => $actor);

        $summary = [
            'success_count' => 0,
            'failed_count' => 0,
        ];
        $chunkSize = max(1, min((int) config('services.vhire.bulk_generate_chunk_size', 25), 50));

        foreach (array_chunk($this->contractIds, $chunkSize) as $chunk) {
            $result = $service->bulkGenerateEmployeeNikAndActivateContracts($chunk, $request);
            $summary['success_count'] += (int) ($result['success_count'] ?? 0);
            $summary['failed_count'] += (int) ($result['failed_count'] ?? 0);
        }

        Log::info('VHIRE BULK NIK ACTIVATION FINISHED', [
            'actor_user_id' => $this->actorUserId,
            'contract_count' => count($this->contractIds),
            'success_count' => $summary['success_count'],
            'failed_count' => $summary['failed_count'],
        ]);
    }
}
