<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_history_items')) {
            return;
        }

        Schema::create('import_history_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_history_id');
            $table->string('category', 20);
            $table->unsignedInteger('row_number')->nullable();
            $table->string('nik', 100)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('message', 500)->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();

            $table->foreign('import_history_id')
                ->references('id')
                ->on('import_histories')
                ->cascadeOnDelete();
            $table->index(['import_history_id', 'category', 'id'], 'import_history_items_history_category_index');
            $table->unique(
                ['import_history_id', 'category', 'row_number'],
                'import_history_items_unique_excel_row'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_history_items');
    }
};
