<?php

namespace App\Http\Requests\LeaveBalance;

use App\Models\LeaveBalanceLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveBalanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'entry_type' => [
                'required',
                Rule::in([
                    LeaveBalanceLedger::TYPE_ANNUAL_GRANT,
                    LeaveBalanceLedger::TYPE_CARRY_OVER,
                    LeaveBalanceLedger::TYPE_ADJUSTMENT,
                    LeaveBalanceLedger::TYPE_EXPIRED,
                ]),
            ],
            'direction' => [
                'nullable',
                'required_if:entry_type,' . LeaveBalanceLedger::TYPE_ADJUSTMENT,
                Rule::in([LeaveBalanceLedger::DIRECTION_CREDIT, LeaveBalanceLedger::DIRECTION_DEBIT]),
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:365'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'transaction_date' => ['required', 'date'],
            'effective_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_type.required' => 'Jenis transaksi saldo cuti wajib dipilih.',
            'entry_type.in' => 'Jenis transaksi saldo cuti tidak valid.',
            'direction.required_if' => 'Arah adjustment wajib dipilih.',
            'amount.required' => 'Jumlah hari wajib diisi.',
            'amount.integer' => 'Jumlah hari harus berupa angka bulat.',
            'amount.min' => 'Jumlah hari minimal 1.',
            'amount.max' => 'Jumlah hari maksimal 365.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'expires_at.after_or_equal' => 'Tanggal expired tidak boleh sebelum tanggal transaksi.',
            'note.required' => 'Catatan HR wajib diisi agar histori saldo cuti jelas.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('expires_at') || !$this->filled('effective_date')) {
                return;
            }

            if (strtotime($this->input('expires_at')) < strtotime($this->input('effective_date'))) {
                $validator->errors()->add('expires_at', 'Tanggal expired tidak boleh sebelum tanggal efektif.');
            }
        });
    }
}
