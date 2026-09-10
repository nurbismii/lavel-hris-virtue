<nav class="cv-workspace-nav" aria-label="Bagian CV Maker" hidden>
    @foreach($workspaceTabs as $key => $tab)
    <button type="button" class="cv-workspace-tab" id="cv-tab-{{ $key }}" data-cv-tab="{{ $key }}" aria-controls="cv-workspace-{{ $key }}">
        <span class="cv-workspace-tab__icon" aria-hidden="true"><i class="fas fa-{{ $tab[0] }}"></i></span>
        <span><strong>{{ $tab[1] }}</strong><small>{{ $tab[2] }}</small></span>
    </button>
    @endforeach
</nav>
