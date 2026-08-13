@php
    $level = $position->effective_level;
    $rank = $level ? (int) $level->rank : 0;
    $assignments = $position->activeAssignments;
    $children = $position->getRelation('chartChildren');
@endphp
<li>
    <article class="org-card org-level-{{ $rank }}">
        <div class="org-card__body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="org-card__title">{{ $position->display_name }}</div>
                <span class="badge bg-primary">{{ optional($level)->code ?: '?' }}</span>
            </div>
            <div class="org-card__meta">Jabatan: {{ optional($position->jobTitle)->display_name ?: 'Belum ditentukan' }}</div>
            <div class="org-card__meta">{{ optional($position->divisi)->nama_divisi ?: 'Tingkat departemen' }} · {{ $position->code }}</div>
            @forelse($assignments as $assignment)
                <div class="org-card__employee">
                    <strong>{{ optional($assignment->employee)->nama_karyawan ?: 'Karyawan tidak ditemukan' }}</strong>
                    <span class="d-block text-muted">NIK {{ $assignment->employee_nik }}</span>
                </div>
            @empty
                <div class="org-card__employee org-card__vacant">
                    <strong>Posisi kosong</strong>
                    <span class="d-block">Kebutuhan {{ $position->planned_headcount }} orang</span>
                </div>
            @endforelse
            @if($assignments->count() < $position->planned_headcount && $assignments->isNotEmpty())
                <div class="org-card__meta text-warning mt-2">Sisa kebutuhan: {{ $position->planned_headcount - $assignments->count() }}</div>
            @endif
        </div>
    </article>
    @if($children->isNotEmpty())
    <ul>
        @foreach($children as $child)
            @include('admin.organization-structure.partials.chart-node', ['position' => $child])
        @endforeach
    </ul>
    @endif
</li>
