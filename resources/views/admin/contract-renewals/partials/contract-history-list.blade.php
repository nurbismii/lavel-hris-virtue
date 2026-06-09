@php
    $historyItems = $historyItems ?? collect();
    $canOpenElectronicContract = auth()->user()
        && auth()->user()->hasRole(['Super Admin', 'HR'])
        && auth()->user()->hasMenuAccess('electronic_contract_admin');
@endphp

@if($historyItems->isNotEmpty())
    <details class="contract-history-details mt-2">
        <summary class="small text-primary">Riwayat kontrak ({{ $historyItems->count() }})</summary>
        <div class="contract-history-list mt-2">
            @foreach($historyItems as $item)
                <div class="contract-history-item">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="badge bg-light text-dark border">{{ $item['raw_type'] ?: $item['type_label'] }}</span>
                        @if(!empty($item['status_label']))
                            <span class="small text-muted">{{ $item['status_label'] }}</span>
                        @endif
                    </div>
                    <div class="small fw-semibold mt-1 text-break">
                        @if($canOpenElectronicContract && !empty($item['source_contract_id']))
                            <a href="{{ route('electronic-contracts.show', $item['source_contract_id']) }}">
                                {{ $item['number'] ?: '-' }}
                            </a>
                        @else
                            {{ $item['number'] ?: '-' }}
                        @endif
                    </div>
                    <div class="small text-muted">
                        Periode:
                        {{ optional($item['start_date'])->format('d M Y') ?: '-' }}
                        s/d
                        {{ optional($item['end_date'])->format('d M Y') ?: '-' }}
                    </div>
                    <div class="small text-muted">
                        TTD:
                        {{ optional($item['signed_at'])->format('d M Y H:i') ?: 'Belum ada data TTD elektronik' }}
                    </div>
                    @if(!empty($item['duration_label']))
                        <div class="small text-muted">Durasi: {{ $item['duration_label'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </details>
@else
    <small class="d-block text-muted mt-2">Belum ada riwayat kontrak.</small>
@endif
