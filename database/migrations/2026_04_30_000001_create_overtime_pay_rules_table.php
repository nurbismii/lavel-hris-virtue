<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overtime_pay_rules')) {
            Schema::create('overtime_pay_rules', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->string('schedule_type', 20);
                $table->string('day_type', 40);
                $table->unsignedSmallInteger('hour_from');
                $table->unsignedSmallInteger('hour_to')->nullable();
                $table->decimal('multiplier', 6, 2);
                $table->string('legal_basis', 160);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['schedule_type', 'day_type', 'is_active', 'sort_order'], 'idx_overtime_pay_rules_lookup');
            });
        }

        if (!Schema::hasTable('overtime_pay_rules') || DB::table('overtime_pay_rules')->exists()) {
            return;
        }

        $now = now();
        DB::table('overtime_pay_rules')->insert([
            [
                'code' => 'PP35_WORKDAY_HOUR_1',
                'name' => 'Hari kerja - jam pertama',
                'schedule_type' => 'any',
                'day_type' => 'workday',
                'hour_from' => 1,
                'hour_to' => 1,
                'multiplier' => 1.50,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (1) huruf a',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_WORKDAY_HOUR_NEXT',
                'name' => 'Hari kerja - jam berikutnya',
                'schedule_type' => 'any',
                'day_type' => 'workday',
                'hour_from' => 2,
                'hour_to' => null,
                'multiplier' => 2.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (1) huruf b',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_OFF_HOLIDAY_HOUR_1_7',
                'name' => '6:1 off/libur resmi - jam 1 sampai 7',
                'schedule_type' => 'six_one',
                'day_type' => 'off_or_holiday',
                'hour_from' => 1,
                'hour_to' => 7,
                'multiplier' => 2.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 1',
                'sort_order' => 110,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_OFF_HOLIDAY_HOUR_8',
                'name' => '6:1 off/libur resmi - jam ke-8',
                'schedule_type' => 'six_one',
                'day_type' => 'off_or_holiday',
                'hour_from' => 8,
                'hour_to' => 8,
                'multiplier' => 3.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 2',
                'sort_order' => 120,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_OFF_HOLIDAY_HOUR_9_11',
                'name' => '6:1 off/libur resmi - jam 9 sampai 11',
                'schedule_type' => 'six_one',
                'day_type' => 'off_or_holiday',
                'hour_from' => 9,
                'hour_to' => 11,
                'multiplier' => 4.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf a angka 3',
                'sort_order' => 130,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_SHORTEST_HOLIDAY_HOUR_1_5',
                'name' => '6:1 libur resmi pada hari kerja terpendek - jam 1 sampai 5',
                'schedule_type' => 'six_one',
                'day_type' => 'shortest_workday_holiday',
                'hour_from' => 1,
                'hour_to' => 5,
                'multiplier' => 2.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 1',
                'sort_order' => 210,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_SHORTEST_HOLIDAY_HOUR_6',
                'name' => '6:1 libur resmi pada hari kerja terpendek - jam ke-6',
                'schedule_type' => 'six_one',
                'day_type' => 'shortest_workday_holiday',
                'hour_from' => 6,
                'hour_to' => 6,
                'multiplier' => 3.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 2',
                'sort_order' => 220,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_6_1_SHORTEST_HOLIDAY_HOUR_7_9',
                'name' => '6:1 libur resmi pada hari kerja terpendek - jam 7 sampai 9',
                'schedule_type' => 'six_one',
                'day_type' => 'shortest_workday_holiday',
                'hour_from' => 7,
                'hour_to' => 9,
                'multiplier' => 4.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (2) huruf b angka 3',
                'sort_order' => 230,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_5_2_OFF_HOLIDAY_HOUR_1_8',
                'name' => '5:2 off/libur resmi - jam 1 sampai 8',
                'schedule_type' => 'five_two',
                'day_type' => 'off_or_holiday',
                'hour_from' => 1,
                'hour_to' => 8,
                'multiplier' => 2.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (3) huruf a',
                'sort_order' => 310,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_5_2_OFF_HOLIDAY_HOUR_9',
                'name' => '5:2 off/libur resmi - jam ke-9',
                'schedule_type' => 'five_two',
                'day_type' => 'off_or_holiday',
                'hour_from' => 9,
                'hour_to' => 9,
                'multiplier' => 3.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (3) huruf b',
                'sort_order' => 320,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PP35_5_2_OFF_HOLIDAY_HOUR_10_12',
                'name' => '5:2 off/libur resmi - jam 10 sampai 12',
                'schedule_type' => 'five_two',
                'day_type' => 'off_or_holiday',
                'hour_from' => 10,
                'hour_to' => 12,
                'multiplier' => 4.00,
                'legal_basis' => 'PP 35/2021 Pasal 31 ayat (3) huruf c',
                'sort_order' => 330,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_pay_rules');
    }
};
