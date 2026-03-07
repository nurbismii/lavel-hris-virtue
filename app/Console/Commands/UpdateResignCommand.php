<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Resign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDataResignKaryawan extends Command
{
    protected $signature = 'update.resign:cron';

    protected $description = 'Update status resign karyawan otomatis';

    public function handle()
    {
        $today = now()->toDateString();

        Resign::whereDate('tanggal_keluar', '<=', $today)
            ->whereNull('flg_kirim')
            ->chunk(200, function ($rows) {

                foreach ($rows as $row) {

                    Employee::where('nik', $row->nik_karyawan)->update([
                        'tgl_resign' => $row->tanggal_keluar,
                        'alasan_resign' => $row->alasan_keluar,
                        'status_resign' => $row->tipe,
                        'kategori_keluar' => $row->tipe
                    ]);

                    $row->update([
                        'flg_kirim' => 1
                    ]);
                }

                Log::info('Resign batch processed: ' . $rows->count());
            });

        return Command::SUCCESS;
    }
}
