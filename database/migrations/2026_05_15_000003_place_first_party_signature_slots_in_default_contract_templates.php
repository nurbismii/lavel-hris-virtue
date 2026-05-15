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
            "PT VIRTUE DRAGON NICKEL INDUSTRY,<br><br><br><br>\n            <strong>AHMAD SAEKUZEN</strong>",
            "PT VIRTUE DRAGON NICKEL INDUSTRY,<br>\n            {{tanda_tangan_pihak_pertama}}\n            <strong>AHMAD SAEKUZEN</strong>"
        );

        $this->placeSignatureSlot(
            'Kontrak Translator - Draft dari Contoh',
            "PIHAK PERTAMA<br><br><br><br>\n            <strong>AHMAD SAEKUZEN</strong>",
            "PIHAK PERTAMA<br>\n            {{tanda_tangan_pihak_pertama}}\n            <strong>AHMAD SAEKUZEN</strong>"
        );

        $this->placeSignatureSlot(
            'Adendum PKWT - VDNI Draft dari Contoh',
            "PIHAK PERTAMA<br><br><br><br>\n            <strong>AHMAD SAEKUZEN</strong>",
            "PIHAK PERTAMA<br>\n            {{tanda_tangan_pihak_pertama}}\n            <strong>AHMAD SAEKUZEN</strong>"
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

        if (strpos($bodyHtml, '{{tanda_tangan_pihak_pertama}}') !== false) {
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
