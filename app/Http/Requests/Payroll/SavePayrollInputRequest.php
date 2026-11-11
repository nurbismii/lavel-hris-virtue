<?php

namespace App\Http\Requests\Payroll;

use App\Services\Payroll\PayrollInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePayrollInputRequest extends FormRequest
{
    public function rules(): array
    {
        $amount = ['nullable','integer','min:0','max:100000000000'];
        $rules = PayrollInputs::periodRules() + [
            'revision'=>['required','integer','min:0'],
            'components'=>['sometimes','array:'.implode(',', array_keys(PayrollInputs::PAY_FIELDS))],
            'components.*'=>$amount,
            'custom'=>['sometimes','array','max:200'],
            'custom.*'=>$amount,
            'save_custom_recurring'=>['sometimes','boolean'],
            'tax'=>['sometimes','array:'.implode(',', array_keys(PayrollInputs::MONTHLY_TAX + PayrollInputs::YTD_FIELDS)).',masa_terakhir'],
            'tax.masa_terakhir'=>['nullable','boolean'],
            'minutes'=>['sometimes','array','max:100'],
            'minutes.*'=>['nullable','integer','min:0','max:1440'],
            'manual_overtime'=>$amount,
            'overtime_note'=>['required_with:manual_overtime','nullable','string','max:255'],
            'separate_reason'=>['nullable','string','max:255'],
        ];
        foreach (PayrollInputs::MONTHLY_TAX as $key=>$label) { $rules['tax.'.$key] = $amount; }
        foreach (PayrollInputs::YTD_FIELDS as $key=>$label) { $rules['tax.'.$key] = ['nullable','integer','min:0','max:1000000000000']; }
        if ($this->boolean('save_recurring')) {
            $rules['recurring'] = ['present','array:'.implode(',', PayrollInputs::RECURRING_KEYS)];
            $rules['recurring.*'] = $amount;
        }
        if ($this->boolean('save_custom_recurring')) {
            $rules['custom_recurring'] = ['present','array','max:200'];
            $rules['custom_recurring.*'] = $amount;
        }
        if ($this->boolean('verify_profile')) {
            $rules['profile'] = ['required','array:'.implode(',', array_keys(PayrollInputs::BPJS_AMOUNTS + PayrollInputs::BPJS_PROGRAMS)).',ptkp,jkk_persen,keluarga_tambahan,bulan_mulai_pajak,nik_npwp_valid'];
            $rules['profile.ptkp'] = ['required',Rule::in(array_keys(config('payroll.ptkp')))];
            $rules['profile.jkk_persen'] = ['required',Rule::in(array_map(fn ($rate) => $rate / 100, config('payroll.jkk_rates')))];
            $rules['profile.keluarga_tambahan'] = ['required','integer','min:0','max:10'];
            $rules['profile.bulan_mulai_pajak'] = ['required','integer','min:1','max:12'];
            $rules['profile.nik_npwp_valid'] = ['accepted'];
            foreach (PayrollInputs::BPJS_AMOUNTS as $key=>$label) { $rules['profile.'.$key] = ['required','integer','min:0','max:100000000000']; }
            foreach (PayrollInputs::BPJS_PROGRAMS as $key=>$label) { $rules['profile.'.$key] = ['required','boolean']; }
        }
        return $rules;
    }

    public function attributes(): array
    {
        $labels = ['profile.ptkp'=>'PTKP', 'profile.jkk_persen'=>'tarif JKK', 'profile.nik_npwp_valid'=>'konfirmasi profil pajak', 'overtime_note'=>'alasan lembur khusus'];
        foreach (PayrollInputs::BPJS_AMOUNTS + PayrollInputs::BPJS_PROGRAMS as $key=>$label) { $labels['profile.'.$key] = $label; }
        foreach (PayrollInputs::PAY_FIELDS as $key=>$label) { $labels['components.'.$key] = $label; }
        foreach (PayrollInputs::RECURRING_KEYS as $key) { $labels['recurring.'.$key] = PayrollInputs::PAY_FIELDS[$key].' berulang'; }
        return $labels;
    }
}
