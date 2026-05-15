<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_templates')) {
            return;
        }

        $this->placeSignatureSlot(
            'PKWT 1 - VDNI Draft dari Contoh',
            "PEKERJA/WORKER,<br><br><br><br>\n            <strong>{{nama_karyawan}}</strong>",
            "PEKERJA/WORKER,<br>\n            {{tanda_tangan_pihak_kedua}}\n            <strong>{{nama_karyawan}}</strong>"
        );

        $this->placeSignatureSlot(
            'Kontrak Translator - Draft dari Contoh',
            "PIHAK KEDUA<br><br><br><br>\n            <strong>{{nama_karyawan}}</strong>",
            "PIHAK KEDUA<br>\n            {{tanda_tangan_pihak_kedua}}\n            <strong>{{nama_karyawan}}</strong>"
        );

        $this->placeSignatureSlot(
            'Adendum PKWT - VDNI Draft dari Contoh',
            "PIHAK KEDUA<br><br><br><br>\n            <strong>{{nama_karyawan}}</strong>",
            "PIHAK KEDUA<br>\n            {{tanda_tangan_pihak_kedua}}\n            <strong>{{nama_karyawan}}</strong>"
        );
    }

    public function down(): void
    {
        // Preserve templates because HR may have edited the content after this migration.
    }

    private function placeSignatureSlot(string $templateName, string $search, string $replace): void
    {
        $template = DB::table('contract_templates')
            ->where('name', $templateName)
            ->whereNull('updated_by')
            ->first(['id', 'body_html']);

        if (!$template) {
            return;
        }

        $bodyHtml = str_replace("\r\n", "\n", (string) $template->body_html);

        if (strpos($bodyHtml, '{{tanda_tangan_pihak_kedua}}') !== false) {
            return;
        }

        $updatedBodyHtml = str_replace($search, $replace, $bodyHtml, $replacementCount);

        if ($replacementCount < 1) {
            return;
        }

        DB::table('contract_templates')
            ->where('id', $template->id)
            ->update([
                'body_html' => $updatedBodyHtml,
                'updated_at' => now(),
            ]);
    }
};
