<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('vhire_candidate_id', 120)->nullable();
            $table->string('candidate_code', 120);
            $table->string('no_ktp', 32);
            $table->string('nama', 180);
            $table->string('jenis_kelamin', 30)->nullable();
            $table->string('status_pernikahan', 60)->nullable();
            $table->text('alamat')->nullable();
            $table->string('jabatan', 180)->nullable();
            $table->date('tanggal_mulai_kerja')->nullable();
            $table->date('tanggal_akhir_kontrak')->nullable();
            $table->string('departemen', 180)->nullable();
            $table->string('lokasi', 180)->nullable();
            $table->string('kode_kontrak', 120)->nullable();
            $table->string('no_pkwt', 120)->nullable();
            $table->decimal('gaji', 18, 2)->nullable();
            $table->decimal('uang_makan', 18, 2)->nullable();
            $table->string('recruitment_status', 80)->nullable();
            $table->string('onboarding_status', 80)->default('pending');
            $table->unsignedInteger('contract_duration_value')->nullable();
            $table->string('contract_duration_unit', 20)->nullable();
            $table->string('signing_method', 20)->default('electronic');
            $table->string('employee_nik', 100)->nullable();
            $table->timestamp('activated_as_employee_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_payload_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('vhire_candidate_id', 'onboarding_candidates_vhire_id_unique');
            $table->unique('candidate_code', 'onboarding_candidates_code_unique');
            $table->index('no_ktp', 'onboarding_candidates_no_ktp_idx');
            $table->index(['onboarding_status', 'created_at'], 'onboarding_candidates_status_created_idx');
            $table->index('employee_nik', 'onboarding_candidates_employee_nik_idx');
        });

        Schema::create('vhire_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 20);
            $table->string('operation', 80);
            $table->string('method', 12)->default('POST');
            $table->string('endpoint', 500);
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->longText('request_payload_summary')->nullable();
            $table->longText('response_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->index(['operation', 'status', 'created_at'], 'vhire_sync_logs_operation_status_idx');
            $table->index(['related_type', 'related_id'], 'vhire_sync_logs_related_idx');
            $table->index('idempotency_key', 'vhire_sync_logs_idempotency_idx');
        });

        if (Schema::hasTable('employee_contracts')) {
            $this->makeContractNikNullable();

            Schema::table('employee_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_contracts', 'onboarding_candidate_id')) {
                    $table->unsignedBigInteger('onboarding_candidate_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('employee_contracts', 'vhire_candidate_id')) {
                    $table->string('vhire_candidate_id', 120)->nullable()->after('onboarding_candidate_id');
                }
                if (!Schema::hasColumn('employee_contracts', 'candidate_code')) {
                    $table->string('candidate_code', 120)->nullable()->after('vhire_candidate_id');
                }
                if (!Schema::hasColumn('employee_contracts', 'no_ktp')) {
                    $table->string('no_ktp', 32)->nullable()->after('candidate_code');
                }
                if (!Schema::hasColumn('employee_contracts', 'employee_nik')) {
                    $table->string('employee_nik', 100)->nullable()->after('nik');
                }
                if (!Schema::hasColumn('employee_contracts', 'candidate_name')) {
                    $table->string('candidate_name', 180)->nullable()->after('employee_nik');
                }
                if (!Schema::hasColumn('employee_contracts', 'departemen')) {
                    $table->string('departemen', 180)->nullable()->after('position');
                }
                if (!Schema::hasColumn('employee_contracts', 'lokasi')) {
                    $table->string('lokasi', 180)->nullable()->after('departemen');
                }
                if (!Schema::hasColumn('employee_contracts', 'duration_value')) {
                    $table->unsignedInteger('duration_value')->nullable()->after('contract_end_date');
                }
                if (!Schema::hasColumn('employee_contracts', 'duration_unit')) {
                    $table->string('duration_unit', 20)->nullable()->after('duration_value');
                }
                if (!Schema::hasColumn('employee_contracts', 'signing_method')) {
                    $table->string('signing_method', 20)->default('electronic')->after('pdf_hash');
                }
                if (!Schema::hasColumn('employee_contracts', 'signature_status')) {
                    $table->string('signature_status', 30)->default('waiting_signature')->after('signing_method');
                }
                if (!Schema::hasColumn('employee_contracts', 'signed_by_source')) {
                    $table->string('signed_by_source', 40)->nullable()->after('signed_at');
                }
                if (!Schema::hasColumn('employee_contracts', 'visible_in_vhire')) {
                    $table->boolean('visible_in_vhire')->default(true)->after('signed_by_source');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_signed_file_path')) {
                    $table->string('manual_signed_file_path', 500)->nullable()->after('visible_in_vhire');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_signed_original_name')) {
                    $table->string('manual_signed_original_name', 255)->nullable()->after('manual_signed_file_path');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_signed_mime_type')) {
                    $table->string('manual_signed_mime_type', 120)->nullable()->after('manual_signed_original_name');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_signed_file_size')) {
                    $table->unsignedBigInteger('manual_signed_file_size')->nullable()->after('manual_signed_mime_type');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_uploaded_by')) {
                    $table->string('manual_uploaded_by', 64)->nullable()->after('manual_signed_file_size');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_uploaded_at')) {
                    $table->timestamp('manual_uploaded_at')->nullable()->after('manual_uploaded_by');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_verification_status')) {
                    $table->string('manual_verification_status', 30)->nullable()->after('manual_uploaded_at');
                }
                if (!Schema::hasColumn('employee_contracts', 'manual_note')) {
                    $table->text('manual_note')->nullable()->after('manual_verification_status');
                }
                if (!Schema::hasColumn('employee_contracts', 'vhire_contract_synced_at')) {
                    $table->timestamp('vhire_contract_synced_at')->nullable()->after('manual_note');
                }
                if (!Schema::hasColumn('employee_contracts', 'vhire_activation_synced_at')) {
                    $table->timestamp('vhire_activation_synced_at')->nullable()->after('vhire_contract_synced_at');
                }
            });

            Schema::table('employee_contracts', function (Blueprint $table) {
                $table->foreign('onboarding_candidate_id', 'employee_contracts_onboarding_candidate_fk')
                    ->references('id')
                    ->on('onboarding_candidates')
                    ->onDelete('set null');
                $table->index(['vhire_candidate_id', 'contract_type'], 'employee_contracts_vhire_type_idx');
                $table->index(['candidate_code', 'contract_type'], 'employee_contracts_candidate_code_type_idx');
                $table->index(['no_ktp', 'contract_type'], 'employee_contracts_no_ktp_type_idx');
                $table->index(['signing_method', 'signature_status'], 'employee_contracts_signing_status_idx');
                $table->index('employee_nik', 'employee_contracts_employee_nik_idx');
            });

            DB::table('employee_contracts')
                ->whereNull('employee_nik')
                ->whereNotNull('nik')
                ->update(['employee_nik' => DB::raw('nik')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_contracts')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                $table->dropForeign('employee_contracts_onboarding_candidate_fk');
                $table->dropIndex('employee_contracts_vhire_type_idx');
                $table->dropIndex('employee_contracts_candidate_code_type_idx');
                $table->dropIndex('employee_contracts_no_ktp_type_idx');
                $table->dropIndex('employee_contracts_signing_status_idx');
                $table->dropIndex('employee_contracts_employee_nik_idx');
            });

            Schema::table('employee_contracts', function (Blueprint $table) {
                $columns = [
                    'vhire_activation_synced_at',
                    'vhire_contract_synced_at',
                    'manual_note',
                    'manual_verification_status',
                    'manual_uploaded_at',
                    'manual_uploaded_by',
                    'manual_signed_file_size',
                    'manual_signed_mime_type',
                    'manual_signed_original_name',
                    'manual_signed_file_path',
                    'visible_in_vhire',
                    'signed_by_source',
                    'signature_status',
                    'signing_method',
                    'duration_unit',
                    'duration_value',
                    'lokasi',
                    'departemen',
                    'candidate_name',
                    'employee_nik',
                    'no_ktp',
                    'candidate_code',
                    'vhire_candidate_id',
                    'onboarding_candidate_id',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('employee_contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            $this->restoreContractNikRequiredForRollback();
        }

        Schema::dropIfExists('vhire_sync_logs');
        Schema::dropIfExists('onboarding_candidates');
    }

    private function makeContractNikNullable(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE employee_contracts MODIFY nik VARCHAR(100) NULL');
    }

    private function restoreContractNikRequiredForRollback(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE employee_contracts SET nik = CONCAT('ONBOARDING-', id) WHERE nik IS NULL");
        DB::statement('ALTER TABLE employee_contracts MODIFY nik VARCHAR(100) NOT NULL');
    }
};
