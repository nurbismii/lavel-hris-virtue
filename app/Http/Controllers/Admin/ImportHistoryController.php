<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ImportHistoryItemsExport;
use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ImportHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'import_type' => ['nullable', Rule::in(array_keys(ImportHistory::typeLabels()))],
            'status' => ['nullable', Rule::in(array_keys(ImportHistory::statusLabels()))],
            'source' => ['nullable', Rule::in(array_keys(ImportHistory::sourceLabels()))],
            'actor' => ['nullable', 'string', 'max:150'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $isTableReady = Schema::hasTable('import_histories');
        $importHistories = null;

        if ($isTableReady) {
            $importHistories = ImportHistory::query()
                ->select([
                    'id',
                    'import_id',
                    'import_type',
                    'module',
                    'source',
                    'status',
                    'file_name',
                    'file_size',
                    'total_rows',
                    'success_count',
                    'failed_count',
                    'skipped_count',
                    'inserted_count',
                    'updated_count',
                    'summary',
                    'failure_samples',
                    'error_message',
                    'created_by',
                    'started_at',
                    'finished_at',
                    'created_at',
                    'updated_at',
                ])
                ->with(['actor:id,name,email'])
                ->when(!$request->user()->canAccessAllEmployees(), function ($query) use ($request) {
                    $query->where('created_by', (string) $request->user()->id);
                })
                ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                    $query->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                    $query->whereDate('created_at', '<=', $dateTo);
                })
                ->when($filters['import_type'] ?? null, function ($query, $type) {
                    $query->where('import_type', $type);
                })
                ->when($filters['status'] ?? null, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($filters['source'] ?? null, function ($query, $source) {
                    $query->where('source', $source);
                })
                ->when($filters['actor'] ?? null, function ($query, $actor) {
                    $query->where(function ($actorQuery) use ($actor) {
                        $actorQuery->where('created_by', $actor)
                            ->orWhereHas('actor', function ($userQuery) use ($actor) {
                                $userQuery->where('name', 'like', $actor . '%')
                                    ->orWhere('email', 'like', $actor . '%');
                            });
                    });
                })
                ->when($filters['search'] ?? null, function ($query, $search) {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->where('file_name', 'like', '%' . $search . '%')
                            ->orWhere('import_id', 'like', $search . '%')
                            ->orWhere('error_message', 'like', '%' . $search . '%');
                    });
                })
                ->latest('created_at')
                ->latest('id')
                ->paginate(50)
                ->withQueryString();
        }

        return view('admin.import-histories.index', [
            'filters' => $filters,
            'importHistories' => $importHistories,
            'isTableReady' => $isTableReady,
            'sourceOptions' => ImportHistory::sourceLabels(),
            'statusOptions' => ImportHistory::statusLabels(),
            'typeOptions' => ImportHistory::typeLabels(),
        ]);
    }

    public function export(Request $request, ImportHistory $importHistory, string $category)
    {
        abort_unless(
            array_key_exists($category, ImportHistoryItem::categoryLabels()),
            404
        );
        abort_unless(
            $request->user()->canAccessAllEmployees()
                || (string) $importHistory->created_by === (string) $request->user()->id,
            403,
            'Anda tidak memiliki akses ke hasil import ini.'
        );

        $expectedCount = $this->categoryCount($importHistory, $category);
        $storedCount = Schema::hasTable('import_history_items')
            ? $importHistory->items()->where('category', $category)->count()
            : 0;

        if ($expectedCount !== $storedCount) {
            toast()->warning(
                'Detail export belum lengkap',
                "Kategori ini memiliki {$expectedCount} data, tetapi detail lengkap yang tersimpan hanya {$storedCount}. History lama tidak diexport secara terpotong; lakukan import baru setelah migration diterapkan."
            );

            return back();
        }

        $label = strtolower(ImportHistoryItem::categoryLabels()[$category]);
        $baseName = pathinfo((string) $importHistory->file_name, PATHINFO_FILENAME) ?: 'import';
        $safeName = Str::slug($baseName) ?: 'import';
        $filename = "history-import-{$safeName}-{$label}-{$importHistory->id}.xlsx";

        return Excel::download(
            new ImportHistoryItemsExport($importHistory, $category),
            $filename
        );
    }

    private function categoryCount(ImportHistory $history, string $category): int
    {
        if ($category === ImportHistoryItem::CATEGORY_FAILED) {
            return (int) $history->failed_count;
        }

        if ($category === ImportHistoryItem::CATEGORY_SKIPPED) {
            return (int) $history->skipped_count;
        }

        return (int) $history->updated_count;
    }
}
