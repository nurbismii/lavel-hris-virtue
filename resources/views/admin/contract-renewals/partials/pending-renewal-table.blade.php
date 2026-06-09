<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>Karyawan</th>
                <th>Keputusan</th>
                <th>Akhir Saat Ini</th>
            </tr>
        </thead>
        <tbody>
            @forelse($renewals as $renewal)
                @php($employee = optional($renewal->employee))
                <tr>
                    <td>
                        <strong>{{ $employee->nama_karyawan ?: '-' }}</strong>
                        <small class="d-block text-muted">{{ $renewal->employee_nik }}</small>
                    </td>
                    <td>
                        {{ $renewal->assessment_label }}
                        @if($renewal->assessment_note)
                            <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($renewal->assessment_note, 70) }}</small>
                        @endif
                    </td>
                    <td>
                        {{ optional($renewal->current_contract_end_date)->format('d M Y') ?: '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-3">{{ $emptyText ?? 'Tidak ada data.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
