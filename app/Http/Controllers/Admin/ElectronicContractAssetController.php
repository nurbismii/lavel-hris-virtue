<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\UploadContractTemplateAssetRequest;
use App\Models\ContractTemplateAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ElectronicContractAssetController extends Controller
{
    public function store(UploadContractTemplateAssetRequest $request)
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            'contract-template-assets/' . now()->format('Y/m'),
            Str::uuid() . '.' . $extension
        );

        $asset = ContractTemplateAsset::create([
            'contract_template_id' => $request->input('contract_template_id'),
            'disk' => config('filesystems.default', 'local'),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'location' => route('electronic-contract-assets.show', $asset),
        ]);
    }

    public function show(Request $request, ContractTemplateAsset $asset)
    {
        abort_unless(
            $request->user()
            && $request->user()->hasMenuAccess(['electronic_contract_admin', 'electronic_contract_user']),
            403
        );

        $path = Storage::path($asset->path);

        abort_unless(File::isFile($path), 404, 'Gambar tidak ditemukan.');

        return response()->file($path, [
            'Content-Type' => $asset->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $asset->original_name ?: basename($asset->path)
            ),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
