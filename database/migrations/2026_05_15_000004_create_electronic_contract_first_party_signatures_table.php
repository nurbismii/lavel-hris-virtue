<?php

use App\Models\ElectronicContractFirstPartySignature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('electronic_contract_first_party_signatures')) {
            Schema::create('electronic_contract_first_party_signatures', function (Blueprint $table) {
                $table->id();
                $table->string('signer_key', 60);
                $table->string('signer_name', 150);
                $table->string('signer_position', 150)->nullable();
                $table->string('signature_path', 500);
                $table->string('signature_source', 30);
                $table->string('updated_by_user_id', 64)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();

                $table->unique('signer_key');
            });
        }

        $this->copyExistingFirstPartySignature();
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_contract_first_party_signatures');
    }

    private function copyExistingFirstPartySignature(): void
    {
        if (
            !Schema::hasTable('employee_contracts') ||
            !Schema::hasColumn('employee_contracts', 'first_party_signature_path')
        ) {
            return;
        }

        $exists = DB::table('electronic_contract_first_party_signatures')
            ->where('signer_key', ElectronicContractFirstPartySignature::SIGNER_KEY)
            ->exists();

        if ($exists) {
            return;
        }

        $contractSignature = DB::table('employee_contracts')
            ->whereNotNull('first_party_signature_path')
            ->orderByDesc('first_party_signed_at')
            ->first([
                'first_party_signature_path',
                'first_party_signature_source',
                'first_party_signed_by_user_id',
                'first_party_signed_at',
            ]);

        if (!$contractSignature) {
            return;
        }

        DB::table('electronic_contract_first_party_signatures')->insert([
            'signer_key' => ElectronicContractFirstPartySignature::SIGNER_KEY,
            'signer_name' => 'AHMAD SAEKUZEN',
            'signer_position' => 'HRD MANAGER',
            'signature_path' => $contractSignature->first_party_signature_path,
            'signature_source' => $contractSignature->first_party_signature_source ?: 'imported',
            'updated_by_user_id' => $contractSignature->first_party_signed_by_user_id,
            'signed_at' => $contractSignature->first_party_signed_at ?: now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
