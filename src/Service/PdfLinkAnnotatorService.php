<?php
namespace App\Service;

use setasign\Fpdi\Fpdi;

class PdfLinkAnnotatorService
{
    public function addLinksToPdf(string $pdfPath, string $outputPath, array $clickouts): void
    {
        $pdf = new Fpdi();

        $pageCount = $pdf->setSourceFile($pdfPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            foreach ($clickouts as $clickout) {
                if ((int)$clickout['pageNumber'] === $pageNo) {
                    $x = $clickout['x'] * $size['width'];
                    $y = $clickout['y'] * $size['height'];
                    $w = $clickout['width'] * $size['width'];
                    $h = $clickout['height'] * $size['height'];

                    $pdf->Link($x, $y, $w, $h, $clickout['url']);
                }
            }
        }

        $pdf->Output($outputPath, 'F');
    }
}
