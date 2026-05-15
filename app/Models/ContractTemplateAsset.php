<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplateAsset extends Model
{
    protected $guarded = [];

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }
}
