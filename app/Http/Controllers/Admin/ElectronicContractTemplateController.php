<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\StoreContractTemplateRequest;
use App\Models\ContractTemplate;
use App\Services\ElectronicContracts\ElectronicContractAuditService;
use App\Services\ElectronicContracts\ElectronicContractService;
use Illuminate\Http\Request;

class ElectronicContractTemplateController extends Controller
{
    public function index()
    {
        return view('admin.electronic-contracts.templates.index', [
            'templates' => ContractTemplate::query()
                ->latest('updated_at')
                ->paginate(20),
            'typeOptions' => ContractTemplate::typeOptions(),
        ]);
    }

    public function create(ElectronicContractService $service)
    {
        return view('admin.electronic-contracts.templates.form', [
            'template' => new ContractTemplate(['is_active' => true]),
            'typeOptions' => ContractTemplate::typeOptions(),
            'variables' => $service->variableLabels(),
            'action' => route('electronic-contracts.templates.store'),
            'method' => 'POST',
        ]);
    }

    public function store(
        StoreContractTemplateRequest $request,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $template = ContractTemplate::create([
            'contract_type' => $request->input('contract_type'),
            'name' => $request->input('name'),
            'letterhead_html' => $service->cleanHtml($request->input('letterhead_html')),
            'body_html' => $service->cleanHtml($request->input('body_html')),
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $audit->record(null, 'template_created', $request, [
            'template_id' => $template->id,
            'contract_type' => $template->contract_type,
        ]);

        toast()->success('Success', 'Template kontrak berhasil dibuat.');
        return redirect()->route('electronic-contracts.templates.index');
    }

    public function edit(ContractTemplate $template, ElectronicContractService $service)
    {
        return view('admin.electronic-contracts.templates.form', [
            'template' => $template,
            'typeOptions' => ContractTemplate::typeOptions(),
            'variables' => $service->variableLabels(),
            'action' => route('electronic-contracts.templates.update', $template),
            'method' => 'PUT',
        ]);
    }

    public function update(
        StoreContractTemplateRequest $request,
        ContractTemplate $template,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $template->update([
            'contract_type' => $request->input('contract_type'),
            'name' => $request->input('name'),
            'letterhead_html' => $service->cleanHtml($request->input('letterhead_html')),
            'body_html' => $service->cleanHtml($request->input('body_html')),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);
        $audit->record(null, 'template_updated', $request, [
            'template_id' => $template->id,
            'contract_type' => $template->contract_type,
        ]);

        toast()->success('Success', 'Template kontrak berhasil diperbarui.');
        return redirect()->route('electronic-contracts.templates.index');
    }

    public function destroy(Request $request, ContractTemplate $template, ElectronicContractAuditService $audit)
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        if ($template->contracts()->exists()) {
            toast()->warning('Peringatan', 'Template sudah dipakai kontrak dan tidak dapat dihapus. Nonaktifkan template jika tidak digunakan lagi.');
            return back();
        }

        $templateId = $template->id;
        $template->delete();
        $audit->record(null, 'template_deleted', $request, [
            'template_id' => $templateId,
        ]);

        toast()->success('Success', 'Template kontrak berhasil dihapus.');
        return redirect()->route('electronic-contracts.templates.index');
    }
}
