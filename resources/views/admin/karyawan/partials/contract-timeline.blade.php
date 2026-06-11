@php
    $timelineItems = collect($contractTimeline ?? [])->values();
    $formatDate = function ($date, string $format = 'd M Y'): string {
        return $date ? \Carbon\Carbon::parse($date)->format($format) : '-';
    };
    $dateKey = function ($date): ?string {
        return $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
    };
    $sortKey = function (array $item) use ($dateKey): string {
        $date = $dateKey($item['end_date'] ?? null) ?: $dateKey($item['start_date'] ?? null) ?: '0000-00-00';

        return $date . '-' . sprintf('%010d', (int) ($item['id'] ?? 0));
    };
    $latestTimelineKey = $timelineItems->isNotEmpty()
        ? $timelineItems->sortByDesc($sortKey)->keys()->first()
        : null;
    $latestTimelineItem = $latestTimelineKey !== null ? $timelineItems->get($latestTimelineKey) : null;
    $electronicContractCount = $timelineItems
        ->filter(fn(array $item) => \Illuminate\Support\Str::contains((string) ($item['source'] ?? ''), 'electronic'))
        ->count();
    $canOpenElectronicContract = auth()->user()
        && auth()->user()->hasRole(['Super Admin', 'HR'])
        && auth()->user()->hasMenuAccess('electronic_contract_admin');
@endphp

<div class="employee-edit-section">
    <div class="employee-edit-section__card">
        <div class="employee-contract-timeline__header">
            <div>
                <div class="employee-edit-section__title">Riwayat Kontrak</div>
                <div class="employee-edit-section__caption">
                    Timeline ini menampilkan riwayat kontrak yang tersedia dari import history dan kontrak elektronik, termasuk karyawan yang belum masuk daftar perpanjangan.
                </div>
            </div>
            <span class="employee-contract-badge employee-contract-badge--soft">
                {{ $timelineItems->count() }} data kontrak
            </span>
        </div>

        @if($timelineItems->isEmpty())
            <div class="employee-contract-empty">
                <div class="employee-contract-empty__icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div>
                    <div class="employee-contract-empty__title">Belum ada riwayat kontrak.</div>
                    <div class="employee-contract-empty__text">
                        Data akan tampil setelah history kontrak diimport atau kontrak elektronik karyawan dibuat.
                    </div>
                </div>
            </div>
        @else
            <div class="employee-contract-summary">
                <div class="employee-contract-summary__item">
                    <span>Total Riwayat</span>
                    <strong>{{ $timelineItems->count() }}</strong>
                </div>
                <div class="employee-contract-summary__item">
                    <span>Kontrak Elektronik</span>
                    <strong>{{ $electronicContractCount }}</strong>
                </div>
                <div class="employee-contract-summary__item">
                    <span>Akhir Kontrak Terbaru</span>
                    <strong>{{ $formatDate($latestTimelineItem['end_date'] ?? null) }}</strong>
                </div>
            </div>

            <div class="employee-contract-timeline" id="employeeContractTimeline">
                @foreach($timelineItems as $item)
                    @php
                        $isLatest = $loop->index === $latestTimelineKey;
                        $detailId = 'employeeContractTimelineDetail' . $loop->iteration;
                        $isPkwt = ($item['history_type'] ?? null) === \App\Models\ContractTemplate::TYPE_PKWT_1;
                        $typeBadgeClass = $isPkwt ? 'employee-contract-badge--primary' : 'employee-contract-badge--info';
                        $sourceLabels = collect(explode('+', (string) ($item['source'] ?? '')))
                            ->map(function ($source) {
                                $source = trim($source);

                                if ($source === 'electronic') {
                                    return 'Kontrak elektronik';
                                }

                                if ($source === 'history') {
                                    return 'History kontrak';
                                }

                                return $source;
                            })
                            ->filter()
                            ->unique()
                            ->implode(' + ');
                    @endphp

                    <article class="employee-contract-timeline__item {{ $isLatest ? 'employee-contract-timeline__item--current employee-contract-timeline__item--active' : '' }}" data-contract-timeline-item>
                        <div class="employee-contract-timeline__marker" aria-hidden="true">
                            <span>{{ $loop->iteration }}</span>
                        </div>
                        <div class="employee-contract-timeline__content">
                            <div class="employee-contract-timeline__card">
                                <div class="employee-contract-timeline__top">
                                    <div class="min-w-0">
                                        <div class="employee-contract-timeline__badges">
                                            <span class="employee-contract-badge {{ $typeBadgeClass }}">
                                                {{ $item['raw_type'] ?: $item['type_label'] ?: 'Kontrak' }}
                                            </span>
                                            @if($isLatest)
                                                <span class="employee-contract-badge employee-contract-badge--success">Terbaru</span>
                                            @endif
                                            @if(!empty($item['status_label']))
                                                <span class="employee-contract-badge employee-contract-badge--soft">{{ $item['status_label'] }}</span>
                                            @endif
                                        </div>
                                        <div class="employee-contract-timeline__number">
                                            @if($canOpenElectronicContract && !empty($item['source_contract_id']))
                                                <a href="{{ route('electronic-contracts.show', $item['source_contract_id']) }}" target="_blank" rel="noopener noreferrer">
                                                    {{ $item['number'] ?: 'Nomor kontrak belum tersedia' }}
                                                </a>
                                            @else
                                                {{ $item['number'] ?: 'Nomor kontrak belum tersedia' }}
                                            @endif
                                        </div>
                                        <div class="employee-contract-timeline__period">
                                            {{ $formatDate($item['start_date'] ?? null) }} s/d {{ $formatDate($item['end_date'] ?? null) }}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary employee-contract-timeline__toggle"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $detailId }}"
                                        aria-expanded="{{ $isLatest ? 'true' : 'false' }}"
                                        aria-controls="{{ $detailId }}"
                                        data-contract-timeline-toggle>
                                        <span class="employee-contract-timeline__toggle-text">{{ $isLatest ? 'Tutup detail' : 'Lihat detail' }}</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>

                                <div class="collapse {{ $isLatest ? 'show' : '' }}" id="{{ $detailId }}" data-contract-timeline-collapse>
                                    <div class="employee-contract-timeline__details">
                                        <div>
                                            <span>Jenis</span>
                                            <strong>{{ $item['type_label'] ?: '-' }}</strong>
                                        </div>
                                        <div>
                                            <span>Urutan</span>
                                            <strong>{{ (int) ($item['sequence'] ?? 0) > 0 ? $item['sequence'] : '-' }}</strong>
                                        </div>
                                        <div>
                                            <span>Durasi</span>
                                            <strong>{{ $item['duration_label'] ?: '-' }}</strong>
                                        </div>
                                        <div>
                                            <span>Sumber Data</span>
                                            <strong>{{ $sourceLabels ?: '-' }}</strong>
                                        </div>
                                        <div>
                                            <span>Ditandatangani</span>
                                            <strong>{{ $formatDate($item['signed_at'] ?? null, 'd M Y H:i') }}</strong>
                                        </div>
                                        <div>
                                            <span>Akhir Kontrak</span>
                                            <strong>{{ $formatDate($item['end_date'] ?? null) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
