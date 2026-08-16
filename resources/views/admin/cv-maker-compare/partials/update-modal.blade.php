<div class="modal fade" id="cvUpdatePreviewModal" tabindex="-1" aria-labelledby="cvUpdatePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cv-update-modal">
            <div class="modal-header">
                <div>
                    <h1 class="modal-title fs-5" id="cvUpdatePreviewModalLabel">Update HRIS dari CV Maker</h1>
                    <small class="text-muted" id="cvUpdatePreviewEmployee">-</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="cvUpdatePreviewLoading" class="cv-update-state">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Memeriksa perubahan dari CV Maker...
                </div>
                <div id="cvUpdatePreviewError" class="alert ui-alert ui-alert--warning d-none mb-0"></div>
                <div id="cvUpdatePreviewEmpty" class="alert ui-alert d-none mb-0"></div>
                <div id="cvUpdatePreviewContent" class="d-none">
                    <div class="cv-update-warning mb-3">
                        <i class="fas fa-info-circle"></i>
                        Pilih hanya data yang telah diverifikasi. Field identitas, keuangan, organisasi, dan wilayah tidak dipilih otomatis. Bagian riwayat mengganti data yang sebelumnya bersumber dari Vitae; data manual/sumber lain tetap dipertahankan. Sinkronisasi struktur organisasi merupakan tindakan terpisah karena dapat membuat master jabatan, posisi, dan penempatan karyawan.
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="cvUpdateSelectSafeButton">
                            <i class="fas fa-check-double"></i> Pilih Field Aman
                        </button>
                        <button type="button" class="btn btn-sm btn-light border" id="cvUpdateClearSelectionButton">Kosongkan Pilihan</button>
                        <span class="small text-muted" id="cvUpdateSelectionSummary">0 item dipilih</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle cv-update-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 44px">Pilih</th>
                                    <th>Field</th>
                                    <th>HRIS sekarang</th>
                                    <th>CV Maker</th>
                                </tr>
                            </thead>
                            <tbody id="cvUpdatePreviewRows"></tbody>
                        </table>
                    </div>
                    <div id="cvUpdatePreviewSkipped" class="cv-update-skipped d-none"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border ui-btn-icon" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <button type="button" class="btn btn-danger ui-btn-icon" id="cvUpdateConfirmButton" disabled>
                    <i class="fas fa-sync-alt"></i>
                    Update Pilihan
                </button>
            </div>
        </div>
    </div>
</div>
