@if(\App\Services\CvMaker\CvMakerPdfExportService::canAccess(auth()->user()))
<section class="card my-3" id="cvPdfPanel" data-index-url="{{ route('cv-maker-compare.pdf.index') }}" data-store-url="{{ route('cv-maker-compare.pdf.store') }}">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h6 mb-0">{{ __('Download PDF CV Maker') }}</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cvPdfRefresh">{{ __('Refresh riwayat') }}</button>
        </div>
        <p class="small text-muted mb-2">Hanya progres CV Maker yang sudah lengkap. Setiap batch mengambil {{ max(1, min(100, (int) config('cv_pdf.batch_limit', 50))) }} CV berikutnya sesuai filter dan menghasilkan ZIP berisi PDF per karyawan. Klik buat batch lagi untuk melanjutkan sisa data.</p>
        @if(isset($employee))
            <button type="button" class="btn btn-outline-danger js-cv-pdf-single" data-nik="{{ $employee->nik }}" data-downloaded="ask"
                @disabled(empty($progressStatus) || !$progressStatus->is_complete || !$progressStatus->cv_profile_id)>{{ __('Download PDF Karyawan Ini') }}</button>
            <label class="small ms-2"><input type="checkbox" class="form-check-input me-1" id="cvPdfAllowDownloaded"> {{ __('Buat ulang jika sudah diunduh') }}</label>
        @else
            <div class="d-flex flex-wrap align-items-center gap-3">
                <button type="button" class="btn btn-danger" id="cvPdfBatchCreate">{{ __('Buat Batch PDF Hasil Filter') }}</button>
                <label class="small mb-0"><input type="checkbox" class="form-check-input me-1" id="cvPdfAllowDownloaded"> {{ __('Sertakan yang sudah diunduh') }}</label>
            </div>
        @endif
        <div class="small text-muted mt-2">{{ __('CV dalam batch aktif tidak dimasukkan ke batch lain. Unduhan ulang tersedia dari riwayat. Status “sudah diunduh” berarti server telah melayani permintaan unduhan.') }}</div>
        <div id="cvPdfFeedback" class="alert alert-info py-2 mt-3 d-none" role="status" aria-live="polite"></div>
        <div id="cvPdfHistory" class="mt-3" aria-live="polite"></div>
        <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cvPdfPrevious">{{ __('Sebelumnya') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cvPdfNext">{{ __('Berikutnya') }}</button>
        </div>
    </div>
</section>
@endif
