<?php

namespace App\Http\Requests\LeaveBalance;

use App\Models\LeaveBalanceLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveBalanceEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'entry_type' => LeaveBalanceLedger::TYPE_ADJUSTMENT,
        ]);
    }

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
                    LeaveBalanceLedger::TYPE_ADJUSTMENT,
                ]),
            ],
            'direction' => [
                'required',
                Rule::in([LeaveBalanceLedger::DIRECTION_CREDIT, LeaveBalanceLedger::DIRECTION_DEBIT]),
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:365'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'transaction_date' => ['required', 'date'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_type.required' => 'Jenis transaksi saldo cuti wajib dipilih.',
            'entry_type.in' => 'Jenis transaksi saldo cuti tidak valid.',
            'direction.required' => 'Arah adjustment wajib dipilih.',
            'amount.required' => 'Jumlah hari wajib diisi.',
            'amount.integer' => 'Jumlah hari harus berupa angka bulat.',
            'amount.min' => 'Jumlah hari minimal 1.',
            'amount.max' => 'Jumlah hari maksimal 365.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'note.required' => 'Catatan HR wajib diisi agar histori saldo cuti jelas.',
        ];
    }
}
