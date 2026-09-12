<div class="employee-edit-section">
    <div class="employee-edit-section__card">
        <div class="employee-edit-section__title">{{ __('Riwayat Pelanggaran') }}</div>
        <div class="employee-edit-section__caption mb-3">{{ __('Surat peringatan karyawan, diurutkan dari tanggal mulai terbaru.') }}</div>

        @forelse ($violationHistories as $violation)
            <article class="employee-violation-card border rounded p-3 mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <span class="badge bg-warning text-dark">{{ $violation->level_sp ?: 'Level belum diisi' }}</span>
                    <span class="small text-muted">{{ __('Catatan') }} #{{ $violationHistories->firstItem() + $loop->index }}</span>
                </div>
                <dl class="row mb-0">
                    <div class="col-12 col-sm-6 mb-2">
                        <dt class="small text-muted">{{ __('Tanggal mulai') }}</dt>
                        <dd class="mb-0">{{ $violation->tgl_mulai ?: '-' }}</dd>
                    </div>
                    <div class="col-12 col-sm-6 mb-2">
                        <dt class="small text-muted">{{ __('Tanggal berakhir') }}</dt>
                        <dd class="mb-0">{{ $violation->tgl_berakhir ?: '-' }}</dd>
                    </div>
                    <div class="col-12">
                        <dt class="small text-muted">{{ __('Keterangan') }}</dt>
                        <dd class="employee-violation-card__description mb-0">{{ $violation->keterangan ?: 'Tidak ada keterangan.' }}</dd>
                    </div>
                </dl>
                <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                    <span class="small text-muted">No. SP: {{ $violation->no_sp }}</span>
                    @if($violation->issuance)
                        @if(auth()->user()->hasMenuAccess('surat_peringatan'))
                            <a href="{{ route('warning-letter-requests.show', $violation->issuance) }}" class="btn btn-sm btn-outline-primary">Lihat surat</a>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="text-center text-muted py-4" role="status">
                <i class="fas fa-clipboard-check fa-2x mb-3" aria-hidden="true"></i>
                <p class="mb-1 fw-semibold">{{ __('Belum ada riwayat pelanggaran.') }}</p>
                <p class="small mb-0">{{ __('Tidak ada surat peringatan yang tercatat untuk karyawan ini.') }}</p>
            </div>
        @endforelse

        @if ($violationHistories->hasPages())
            <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="{{ __('Halaman riwayat pelanggaran') }}">
                <span class="small text-muted">{{ $violationHistories->firstItem() }}–{{ $violationHistories->lastItem() }} {{ __('dari') }} {{ $violationHistories->total() }}</span>
                <div class="d-flex gap-2">
                    @if ($violationHistories->previousPageUrl())
                        <a class="btn btn-sm btn-outline-primary" href="{{ $violationHistories->previousPageUrl() }}">{{ __('Sebelumnya') }}</a>
                    @endif
                    @if ($violationHistories->nextPageUrl())
                        <a class="btn btn-sm btn-outline-primary" href="{{ $violationHistories->nextPageUrl() }}">{{ __('Berikutnya') }}</a>
                    @endif
                </div>
            </nav>
        @endif
    </div>
</div>
