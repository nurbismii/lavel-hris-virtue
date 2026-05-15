<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\StoreContractClauseRequest;
use App\Models\ContractClause;
use App\Services\ElectronicContracts\ElectronicContractAuditService;
use App\Services\ElectronicContracts\ElectronicContractService;
use Illuminate\Http\Request;

class ElectronicContractClauseController extends Controller
{
    public function index()
    {
        return view('admin.electronic-contracts.clauses.index', [
            'clauses' => ContractClause::query()
                ->orderBy('clause_key')
                ->get(),
            'keyOptions' => ContractClause::keyOptions(),
        ]);
    }

    public function create(ElectronicContractService $service)
    {
        return view('admin.electronic-contracts.clauses.form', [
            'clause' => new ContractClause(['is_active' => true]),
            'keyOptions' => ContractClause::keyOptions(),
            'variables' => $service->variableLabels(),
            'action' => route('electronic-contracts.clauses.store'),
            'method' => 'POST',
        ]);
    }

    public function store(
        StoreContractClauseRequest $request,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $clause = ContractClause::create([
            'clause_key' => $request->input('clause_key'),
            'name' => $request->input('name'),
            'body_html' => $service->cleanHtml($request->input('body_html')),
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $audit->record(null, 'clause_created', $request, [
            'clause_id' => $clause->id,
            'clause_key' => $clause->clause_key,
        ]);

        toast()->success('Success', 'Klausul adendum berhasil dibuat.');
        return redirect()->route('electronic-contracts.clauses.index');
    }

    public function edit(ContractClause $clause, ElectronicContractService $service)
    {
        return view('admin.electronic-contracts.clauses.form', [
            'clause' => $clause,
            'keyOptions' => ContractClause::keyOptions(),
            'variables' => $service->variableLabels(),
            'action' => route('electronic-contracts.clauses.update', $clause),
            'method' => 'PUT',
        ]);
    }

    public function update(
        StoreContractClauseRequest $request,
        ContractClause $clause,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $clause->update([
            'clause_key' => $request->input('clause_key'),
            'name' => $request->input('name'),
            'body_html' => $service->cleanHtml($request->input('body_html')),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);
        $audit->record(null, 'clause_updated', $request, [
            'clause_id' => $clause->id,
            'clause_key' => $clause->clause_key,
        ]);

        toast()->success('Success', 'Klausul adendum berhasil diperbarui.');
        return redirect()->route('electronic-contracts.clauses.index');
    }

    public function destroy(Request $request, ContractClause $clause, ElectronicContractAuditService $audit)
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        if ($clause->clause_key === ContractClause::KEY_CLAUSE_2) {
            toast()->warning('Peringatan', 'Klausul 2 wajib tersedia untuk adendum kedua dan seterusnya.');
            return back();
        }

        $clauseId = $clause->id;
        $clause->delete();
        $audit->record(null, 'clause_deleted', $request, [
            'clause_id' => $clauseId,
        ]);

        toast()->success('Success', 'Klausul adendum berhasil dihapus.');
        return redirect()->route('electronic-contracts.clauses.index');
    }
}
