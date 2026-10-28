<?php

namespace Tests\Feature;

use App\Models\Epayslip\Karyawan;
use App\Models\Epayslip\KomponenGaji;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class PdfUiLayoutTest extends TestCase
{
    public function test_payslips_load_styles_and_embed_chinese_glyphs_for_long_names(): void
    {
        $employee = new Karyawan(['nik' => 'TEST-UI-001', 'nama' => str_repeat('Nama Uji Panjang ', 4) . '张伟 中文']);
        $slip = new KomponenGaji([
            'mulai_periode' => '2026-09-16', 'akhir_periode' => '2026-10-15', 'periode' => '2026-10',
            'departemen' => str_repeat('Departemen Produksi ', 4) . '生产管理',
            'gaji_pokok' => 8000000, 'tot_diterima' => 8000000,
        ]);
        $slip->setRelation('karyawan', $employee);
        foreach (['user.slip-gaji.pdf', 'admin.slip-gaji.pdf'] as $view) {
            $pdf = Pdf::loadView($view, compact('slip'))->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
            $heroStyle = null;
            $pdf->getDomPDF()->setCallbacks([['event' => 'begin_frame', 'f' => function ($frame) use (&$heroStyle) {
                $node = $frame->get_node();
                if ($node instanceof \DOMElement && $node->getAttribute('class') === 'pdf-hero') {
                    $heroStyle = $frame->get_style();
                }
            }]]);
            $bytes = $pdf->output();
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
            $this->assertLessThan(1048576, strlen($bytes));
            foreach (['normal', 'bold'] as $weight) {
                $font = $pdf->getDomPDF()->getFontMetrics()->getFont('CvNotoSansSC', $weight);
                $this->assertNotNull($font);
                foreach (['张', '伟', '中', '文', '生', '产', '管', '理'] as $char) {
                    $this->assertTrue($pdf->getDomPDF()->getCanvas()->font_supports_char($font, $char), $view . ': ' . $char);
                }
            }
            if ($view === 'user.slip-gaji.pdf') {
                $this->assertNotNull($heroStyle);
                $this->assertNotSame('transparent', $heroStyle->background_color);
                $this->assertGreaterThan(0, $heroStyle->padding_top);
            }
        }
    }
}
