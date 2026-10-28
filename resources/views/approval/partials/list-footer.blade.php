@if($items->isEmpty())
<div class="ui-empty-state" role="status"><i class="fas fa-inbox" aria-hidden="true"></i><span>{{ __('Tidak ada pengajuan yang sesuai dengan pencarian.') }}</span></div>
@endif
<div class="approval-list-footer">
    <p role="status">{{ __(':count pengajuan ditemukan.', ['count' => number_format($items->total())]) }}</p>
    {{ $items->links() }}
</div>
