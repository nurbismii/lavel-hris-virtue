@if(collect($items)->isEmpty())
    <div class="alert alert-light border mb-0 small text-muted">{{ $emptyMessage ?? 'Belum ada data progress.' }}</div>
@else
    <div class="row g-3">
        @foreach($items as $item)
            <div class="col-md-6 col-xl-4">
                <div class="upload-progress-card p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="fw-semibold">{{ $item['label'] }}</div>
                            <div class="upload-progress-card__meta">Update {{ $item['updated_at_human'] }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $item['status_class'] }}">{{ $item['status_label'] }}</span>
                            @if(!empty($item['delete_url']))
                                <button
                                    type="button"
                                    class="btn btn-sm btn-link text-muted p-0 upload-progress-delete"
                                    data-delete-url="{{ $item['delete_url'] }}"
                                    aria-label="Hapus progress {{ $item['label'] }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div
                            class="progress-bar bg-{{ $item['status_class'] }}"
                            role="progressbar"
                            style="width: {{ $item['progress_percentage'] }}%;"
                            aria-valuenow="{{ $item['progress_percentage'] }}"
                            aria-valuemin="0"
                            aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between upload-progress-card__counts">
                        <span>{{ $item['processed_entries'] }}/{{ $item['total_entries'] }} file</span>
                        <span>{{ $item['progress_percentage'] }}%</span>
                    </div>
                    <div class="upload-progress-card__meta mt-2">
                        Berhasil {{ $item['success_count'] }} file, dilewati {{ $item['skipped_count'] }} file.
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
