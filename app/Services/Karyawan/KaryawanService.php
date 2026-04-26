<?php

namespace App\Services\Karyawan;

use App\Models\Employee;
use Yajra\DataTables\Facades\DataTables;

class KaryawanService
{
    private const DOCUMENT_FIELDS = [
        'photo' => ['column' => 'photo_path', 'label' => 'Foto'],
        'ktp' => ['column' => 'ktp_path', 'label' => 'KTP'],
        'kk' => ['column' => 'kk_path', 'label' => 'KK'],
        'sim' => ['column' => 'sim_path', 'label' => 'SIM'],
        'sio' => ['column' => 'sio_path', 'label' => 'SIO'],
        'face_reference' => ['column' => 'face_reference_path', 'label' => 'Face Ref'],
    ];

    // Service methods for Karyawan can be implemented here
    public function getDataKaryawan($request, $user)
    {
        // Logic to retrieve and filter Karyawan data
        $query = Employee::select(
            'nik',
            'nama_karyawan',
            'area_kerja',
            'departemen_id',
            'divisi_id',
            'status_resign',
            'posisi',
            'photo_path',
            'ktp_path',
            'kk_path',
            'sim_path',
            'sio_path',
            'face_reference_path'
        )
            ->whereNotNull('status_resign')
            ->with(['departemen', 'divisi']);

        $user->applyEmployeeScope($query);

        if ($request->area) {
            $query->where('area_kerja', $request->area);
        }

        if ($request->departemen) {
            $query->where('departemen_id', $request->departemen);
        }

        if ($request->divisi) {
            $query->where('divisi_id', $request->divisi);
        }

        if ($request->status_resign) {
            $query->where('status_resign', $request->status_resign);
        }

        return DataTables::of($query)
            ->addColumn('area', fn($r) => $r->area_kerja ?? '-')
            ->addColumn('departemen', fn($r) => $r->departemen->departemen ?? '-')
            ->addColumn('divisi', fn($r) => $r->divisi->nama_divisi ?? '-')
            ->addColumn('status', fn($r) => $r->status_resign ?? '-')
            ->addColumn('dokumen', fn($r) => $this->renderDocumentSummary($r))
            ->addColumn('aksi', function ($r) {
                $editButton = '
                    <a href="' . route('karyawan.edit', $r->nik) . '" 
                       class="btn btn-sm btn-warning me-1">
                        <i class="fa fa-edit"></i>
                    </a>
                ';

                $deleteButton = auth()->user()->canAccessAllEmployees() ? '
                    <button class="btn btn-sm btn-danger btn-delete"
                        data-id="' . $r->nik . '"
                        data-nama="' . $r->nama_karyawan . '">
                        <i class="fa fa-trash"></i>
                    </button>
                ' : '';

                return $editButton . $deleteButton;
            })
            ->rawColumns(['aksi', 'dokumen'])
            ->make(true);
    }

    private function renderDocumentSummary(Employee $employee): string
    {
        $completed = collect(self::DOCUMENT_FIELDS)
            ->filter(fn($config) => filled($employee->{$config['column']}))
            ->count();
        $total = count(self::DOCUMENT_FIELDS);
        $summaryClass = $completed === $total
            ? 'document-summary__count--complete'
            : ($completed > 0 ? 'document-summary__count--partial' : 'document-summary__count--empty');

        $links = collect(self::DOCUMENT_FIELDS)
            ->map(function ($config, $type) use ($employee) {
                $label = $config['label'];
                $column = $config['column'];
                $isAvailable = filled($employee->{$column});

                if (!$isAvailable) {
                    return sprintf(
                        '<span class="document-link document-link--missing" title="%s belum ada">%s</span>',
                        e($label),
                        e($label)
                    );
                }

                return sprintf(
                    '<a href="%s" class="document-link document-link--ready" title="Download %s">%s</a>',
                    e(route('karyawan.documents.download', ['nik' => $employee->nik, 'type' => $type])),
                    e($label),
                    e($label)
                );
            })
            ->implode('');

        return sprintf(
            '<div class="document-summary"><span class="document-summary__count %s">%d/%d</span><div class="document-summary__links">%s</div></div>',
            $summaryClass,
            $completed,
            $total,
            $links
        );
    }
}
