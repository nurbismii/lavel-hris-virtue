@php($result = app(\App\Services\Severance\SeveranceResultFormatter::class)->format($result))
<div class="table-responsive"><table class="table severance-result"><thead><tr><th>Komponen</th><th>Perhitungan</th><th>Nominal (Rp)</th></tr></thead><tbody>
@foreach($result['lines'] as $line)<tr><td>{{ $line['label'] }}</td><td data-label="Perhitungan">{{ $line['formula'] }}</td><td data-label="Nominal (Rp)" class="text-end">{{ number_format($line['amount'], 2, ',', '.') }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="2">{{ $result['total_label'] }}<small>Bruto sebelum pajak</small></th><th class="text-end">{{ number_format($result['total'], 2, ',', '.') }}</th></tr></tfoot></table></div>
