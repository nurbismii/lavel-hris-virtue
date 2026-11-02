@if(trim((string) $slot) !== '')
<li class="nav-item app-nav-group {{ $active ? 'active' : '' }}">
    <button type="button" class="app-nav-group-toggle {{ $active ? '' : 'collapsed' }}"
        data-bs-toggle="collapse" data-bs-target="#{{ $id }}"
        aria-controls="{{ $id }}" aria-expanded="{{ $active ? 'true' : 'false' }}"
        title="{{ $label }}">
        <i class="{{ $icon }}" aria-hidden="true"></i>
        <span class="app-nav-label">{{ $label }}</span>
        @if(($badge ?? 0) > 0)
        <span class="badge badge-primary">{{ $badge }}</span>
        @endif
        <i class="fas fa-chevron-down app-nav-caret" aria-hidden="true"></i>
    </button>
    <div class="collapse {{ $active ? 'show' : '' }}" id="{{ $id }}" data-bs-parent="#appNavigation">
        <ul class="nav nav-collapse app-nav-children">
            {{ $slot }}
        </ul>
    </div>
</li>
@endif
