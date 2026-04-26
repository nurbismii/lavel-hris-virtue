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
            'no_ktp',
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
                $mime = $this->guessDocumentMime($employee->{$column});

                if (!$isAvailable) {
                    return sprintf(
                        '<span class="document-link document-link--missing" title="%s belum ada">%s</span>',
                        e($label),
                        e($label)
                    );
                }

                $previewUrl = route('karyawan.documents.preview', ['nik' => $employee->nik, 'type' => $type]);
                $downloadUrl = route('karyawan.documents.download', ['nik' => $employee->nik, 'type' => $type]);

                return sprintf(
                    '<a href="%s" class="document-link document-link--ready js-document-preview" title="Preview %s" data-preview-url="%s" data-download-url="%s" data-document-label="%s" data-document-mime="%s">%s</a>',
                    e($previewUrl),
                    e($label),
                    e($previewUrl),
                    e($downloadUrl),
                    e($label),
                    e($mime),
                    e($label)
                );
            })
            ->implode('');

        return sprintf(
            '<div class="document-summary"><span class="document-summary__count %s">%d/%d</span><div class="document-summary__links">%s%s</div></div>',
            $summaryClass,
            $completed,
            $total,
            $links,
            $this->renderRecruitmentDocumentButton($employee)
        );
    }

    private function renderRecruitmentDocumentButton(Employee $employee): string
    {
        if (blank($employee->no_ktp)) {
            return '<span class="document-link document-link--missing" title="No KTP belum tersedia">Recruitment</span>';
        }

        return sprintf(
            '<button type="button" class="document-link document-link--recruitment js-recruitment-documents" data-url="%s" data-employee-name="%s" data-no-ktp="%s">Recruitment</button>',
            e(route('karyawan.recruitment-documents', ['nik' => $employee->nik])),
            e($employee->nama_karyawan),
            e($employee->no_ktp)
        );
    }

    private function guessDocumentMime(?string $path): string
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
        }

        if ($extension === 'pdf') {
            return 'application/pdf';
        }

        return '';
    }
}
