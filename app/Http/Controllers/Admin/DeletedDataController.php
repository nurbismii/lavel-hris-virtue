<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recovery\DeletedDataService;
use Illuminate\Http\Request;

class DeletedDataController extends Controller
{
    public function index(Request $request, DeletedDataService $service)
    {
        $filters = $request->validate(['type'=>['nullable','in:'.implode(',', array_keys(DeletedDataService::TYPES))], 'q'=>['nullable','string','max:100']]);
        $types = array_filter(DeletedDataService::TYPES, fn ($meta) => $request->user()->hasMenuAccess($meta[3]));
        abort_unless($types, 403);
        $type = $filters['type'] ?? array_key_first($types);
        $meta = DeletedDataService::TYPES[$type];
        $search = trim($filters['q'] ?? '');
        $query = $service->query($type, $request->user())->onlyTrashed();
        $key = $query->getModel()->getKeyName();
        $items = $query->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where($key, $search)->orWhere($meta[2], 'like', $search.'%')))
            ->orderByDesc('deleted_at')->paginate(40)->withQueryString();
        return response()->view('admin.recovery.index', compact('items', 'types', 'type', 'meta', 'search'))->header('Cache-Control', 'private, no-store');
    }

    public function restore(Request $request, string $type, string $id, DeletedDataService $service)
    {
        $service->change($type, $id, $request->user(), true);
        return response()->json(['success'=>true, 'message'=>'Data berhasil dipulihkan.']);
    }
}
