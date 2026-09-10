<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class CvMakerPdfMandarinTest extends TestCase
{
    public function test_cv_pdf_maps_chinese_glyphs_in_regular_and_bold_text(): void
    {
        $text = '张伟 中文 生产主管 工作经验 教育背景';
        $pdf = Pdf::loadView('admin.cv-maker-compare.pdf', [
            'nik' => 'TEST-001', 'generatedAt' => '10/09/2026 10:00',
            'vitae' => [
                'profile' => ['name' => '张伟 - Budi Santoso', 'position' => '生产主管 / Supervisor Produksi',
                    'summary' => $text, 'address' => '中国北京市 - Morosi, Indonesia'],
                'experiences' => [['title' => '生产管理工程师', 'company' => '德龙镍业有限公司',
                    'period' => '2020 - Sekarang', 'responsibilities' => ['负责生产管理、安全检查和团队协调。']]],
                'educations' => [['title' => '机械工程', 'institution' => '北京大学', 'year' => '2015']],
            ],
        ])->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'isJavascriptEnabled' => false, 'isFontSubsettingEnabled' => true,
            'fontDir' => str_replace('\\', '/', storage_path('fonts')),
            'fontCache' => str_replace('\\', '/', storage_path('fonts'))]);
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertLessThan(1048576, strlen($bytes), 'Subset PDF should not embed the entire CJK font.');
        $metrics = $pdf->getDomPDF()->getFontMetrics();
        foreach (['normal', 'bold'] as $weight) {
            $font = $metrics->getFont('CvNotoSansSC', $weight);
            $this->assertNotNull($font, 'CJK font must be registered for ' . $weight . ': ' . json_encode($GLOBALS['_dompdf_warnings'] ?? []));
            foreach (preg_split('//u', str_replace(' ', '', $text), -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $this->assertTrue($pdf->getDomPDF()->getCanvas()->font_supports_char($font, $char), 'Missing glyph: ' . $char);
            }
        }
    }
}
