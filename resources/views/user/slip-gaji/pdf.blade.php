<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>PT VDNI - E-Payslip</title>

    <link rel="icon" href="{{ asset('/assets/img/kaiadmin/favicon.ico') }}" type="image/x-icon" />
    <link rel="stylesheet" href="file:///{{ str_replace('\\', '/', public_path('assets/css/user-slip-gaji-pdf.css')) }}">
</head>

<body>
    @php
    $employeeName = $slip->karyawan->nama
    ?? $slip->karyawan->nama_karyawan
    ?? '-';

    $isPaid = ($slip->status_gaji ?? 'UNPAID') === 'PAID';

    $incomeItems = [
    ['label' => __('self_service.salary.items.basic_salary'), 'value' => (float) ($slip->gaji_pokok ?? 0), 'show' => true],
    ['label' => __('self_service.salary.items.meal_allowance'), 'value' => (float) ($slip->tunj_um ?? 0), 'show' => !empty($slip->tunj_um)],
    ['label' => __('self_service.salary.items.tenure_allowance'), 'value' => (float) ($slip->tunj_mk ?? 0), 'show' => !empty($slip->tunj_mk)],
    ['label' => __('self_service.salary.items.coefficient_allowance'), 'value' => (float) ($slip->tunj_koefisien ?? 0), 'show' => !empty($slip->tunj_koefisien)],
    ['label' => __('self_service.salary.items.supervisor_allowance'), 'value' => (float) ($slip->tunj_pengawas ?? 0), 'show' => !empty($slip->tunj_pengawas)],
    ['label' => __('self_service.salary.items.transport_allowance'), 'value' => (float) ($slip->tunj_transport ?? 0), 'show' => !empty($slip->tunj_transport)],
    ['label' => __('self_service.salary.items.functional_allowance'), 'value' => (float) ($slip->tunj_fungsional ?? 0), 'show' => !empty($slip->tunj_fungsional)],
    ['label' => __('self_service.salary.items.overtime'), 'value' => (float) ($slip->ot ?? 0), 'show' => !empty($slip->ot)],
    ['label' => __('self_service.salary.items.machine_hour'), 'value' => (float) ($slip->hm ?? 0), 'show' => !empty($slip->hm)],
    ['label' => __('self_service.salary.items.rapel'), 'value' => (float) ($slip->rapel ?? 0), 'show' => !empty($slip->rapel)],
    [
    'label' => __('self_service.salary.items.incentive_bonus'),
    'value' => (float) (($slip->insentif ?? 0) + ($slip->bonus ?? 0)),
    'show' => !empty($slip->insentif) || !empty($slip->bonus),
    ],
    ['label' => __('self_service.salary.items.field_allowance'), 'value' => (float) ($slip->tunj_lap ?? 0), 'show' => !empty($slip->tunj_lap)],
    ['label' => __('self_service.salary.items.thr'), 'value' => (float) ($slip->thr ?? 0), 'show' => !empty($slip->thr)],
    ];

    $deductionItems = [
    ['label' => __('self_service.salary.items.health_bpjs'), 'value' => (float) ($slip->pot_bpjskes ?? 0), 'show' => !empty($slip->pot_bpjskes)],
    ['label' => __('self_service.salary.items.jht'), 'value' => (float) ($slip->jht ?? 0), 'show' => !empty($slip->jht)],
    ['label' => __('self_service.salary.items.jp'), 'value' => (float) ($slip->jp ?? 0), 'show' => !empty($slip->jp)],
    ['label' => __('self_service.salary.items.unpaid_leave'), 'value' => (float) ($slip->unpaid_leave ?? 0), 'show' => !empty($slip->unpaid_leave)],
    ['label' => __('self_service.salary.items.pph21'), 'value' => (float) ($slip->deduction_pph21 ?? 0), 'show' => !empty($slip->deduction_pph21)],
    ['label' => __('self_service.salary.items.absence'), 'value' => (float) ($slip->deduction_alpa ?? 0), 'show' => !empty($slip->deduction_alpa)],
    ['label' => __('self_service.salary.items.other_deduction'), 'value' => (float) ($slip->deduction ?? 0), 'show' => !empty($slip->deduction)],
    ];

    $incomeItems = array_values(array_filter($incomeItems, fn ($item) => $item['show']));
    $deductionItems = array_values(array_filter($deductionItems, fn ($item) => $item['show']));

    $totalIncome = array_sum(array_column($incomeItems, 'value'));
    $totalDeduction = array_sum(array_column($deductionItems, 'value'));

    $periodText = '-';

    if (!empty($slip->mulai_periode) && !empty($slip->akhir_periode)) {
    $periodText = formatDateIndonesia($slip->mulai_periode) . ' - ' . formatDateIndonesia($slip->akhir_periode);
    } elseif (!empty($slip->periode)) {
    $periodText = $slip->periode;
    }
    @endphp

    {{-- HEADER --}}
    <div class="pdf-hero">
        <table width="100%" class="pdf-hero-table">
            <tr>
                <td width="16%" class="pdf-logo-cell">
                    <img src="{{ public_path('assets/img/logo-company.png') }}" class="pdf-logo">
                </td>

                <td width="58%">
                    <div class="pdf-kicker">V-Payslip</div>
                    <div class="pdf-title">{{ __('self_service.salary.pdf_title') }}</div>
                    <div class="pdf-subtitle">
                        PT Virtue Dragon Nickel Industry
                    </div>
                    <div class="pdf-period">
                        {{ __('self_service.salary.period', ['period' => $periodText]) }}
                    </div>
                </td>

                <td width="26%" class="pdf-status-cell">
                    <div class="pdf-status {{ $isPaid ? 'pdf-status-paid' : 'pdf-status-unpaid' }}">
                        {{ $slip->status_gaji ?? 'UNPAID' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- TOTAL SUMMARY --}}
    <table width="100%" class="summary-table">
        <tr>
            <td width="58%" class="summary-left">
                <div class="summary-label">{{ __('self_service.salary.total_received') }}</div>
                <div class="summary-amount">
                    Rp {{ number_format($slip->tot_diterima ?? 0, 0, ',', '.') }}
                </div>
            </td>

            <td width="42%" class="summary-right">
                <table width="100%">
                    <tr>
                        <td class="summary-meta-label">{{ __('tables.name') }}</td>
                        <td class="summary-meta-value">{{ $employeeName }}</td>
                    </tr>
                    <tr>
                        <td class="summary-meta-label">NIK</td>
                        <td class="summary-meta-value">{{ $slip->karyawan->nik ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="summary-meta-label">{{ __('tables.period') }}</td>
                        <td class="summary-meta-value">{{ $slip->periode ?? $periodText }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- INFO KARYAWAN --}}
    <div class="section-box">
        <div class="section-heading">
            {{ __('self_service.salary.employee_information_upper') }}
        </div>

        <table width="100%" class="info-table">
            <tr>
                <th width="18%">{{ __('tables.nik') }}</th>
                <td width="32%">{{ $slip->karyawan->nik ?? '-' }}</td>
                <th width="18%">{{ __('tables.name') }}</th>
                <td width="32%">{{ $employeeName }}</td>
            </tr>
            <tr>
                <th>{{ __('tables.department') }}</th>
                <td>{{ $slip->departemen ?? '-' }}</td>
                <th>{{ __('tables.division') }}</th>
                <td>{{ $slip->divisi ?? '-' }}</td>
            </tr>
            <tr>
                <th>{{ __('tables.position') }}</th>
                <td>{{ $slip->posisi ?? '-' }}</td>
                <th>{{ __('tables.total_attendance') }}</th>
                <td>{{ $slip->jml_hari_kerja ?? '-' }}</td>
            </tr>
            <tr>
                <th>{{ __('tables.machine_hour') }}</th>
                <td>{{ $slip->jml_hour_machine ?? '-' }}</td>
                <th>{{ __('tables.warning_duration') }}</th>
                <td>{{ empty($slip->durasi_sp) || $slip->durasi_sp == '0000-00-00' ? '-' : $slip->durasi_sp }}</td>
            </tr>
        </table>
    </div>

    {{-- DETAIL GAJI --}}
    <table width="100%" class="salary-columns">
        <tr>
            {{-- PENDAPATAN --}}
            <td width="50%" class="salary-column salary-column-left">
                <div class="salary-box">
                    <table width="100%">
                        <tr>
                            <td class="salary-box-title income-title">
                                {{ __('self_service.salary.income_upper') }}
                            </td>
                            <td class="salary-box-total">
                                Rp {{ number_format($totalIncome, 0, ',', '.') }}
                            </td>
                        </tr>
                    </table>

                    <table width="100%" class="salary-detail-table">
                        @forelse($incomeItems as $item)
                        <tr>
                            <td>{{ $item['label'] }}</td>
                            <td class="text-right income-value">
                                {{ number_format($item['value'] ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="empty-text">{{ __('self_service.salary.empty_income') }}</td>
                        </tr>
                        @endforelse
                    </table>
                </div>
            </td>

            {{-- POTONGAN --}}
            <td width="50%" class="salary-column salary-column-right">
                <div class="salary-box">
                    <table width="100%">
                        <tr>
                            <td class="salary-box-title deduction-title">
                                {{ __('self_service.salary.deduction_upper') }}
                            </td>
                            <td class="salary-box-total">
                                Rp {{ number_format($totalDeduction, 0, ',', '.') }}
                            </td>
                        </tr>
                    </table>

                    <table width="100%" class="salary-detail-table">
                        @forelse($deductionItems as $item)
                        <tr>
                            <td>{{ $item['label'] }}</td>
                            <td class="text-right deduction-value">
                                {{ number_format($item['value'] ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="empty-text">{{ __('self_service.salary.empty_deduction') }}</td>
                        </tr>
                        @endforelse
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- FINAL TOTAL --}}
    <table width="100%" class="final-total-table">
        <tr>
            <td width="55%">
                <div class="final-total-label">{{ __('self_service.salary.total_received_upper') }}</div>
                <div class="final-total-note">
                    {{ __('self_service.salary.final_total_note') }}
                </div>
            </td>
            <td width="45%" class="final-total-amount">
                Rp {{ number_format($slip->tot_diterima ?? 0, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <table width="100%" class="pdf-footer-table">
        <tr>
            <td width="58%" class="pdf-footer-note">
                <strong>{{ __('self_service.salary.note_label') }}</strong><br>
                {{ __('self_service.salary.pdf_note') }}
            </td>

            <td width="42%" class="pdf-footer-bank">
                <div class="bank-box">
                    <div class="bank-title">{{ __('self_service.salary.bank_information') }}</div>
                    <strong>{{ $employeeName }}</strong><br>
                    {{ __('self_service.salary.bank') }}: {{ $slip->bank_name ?? '-' }}<br>
                    {{ __('self_service.salary.bank_account_number') }}: {{ $slip->bank_number ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

</body>

</html>
