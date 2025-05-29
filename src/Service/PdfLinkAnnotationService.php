<?php
namespace App\Service;

class PdfLinkAnnotationService
{
    public function annotatePdf(string $inputPdf, array $clickouts, string $outputPdf): void
    {
        $clickoutsJsonPath = sys_get_temp_dir() . '/clickouts_' . uniqid() . '.json';
        file_put_contents($clickoutsJsonPath, json_encode($clickouts));

        $pythonScriptPath = __DIR__ . '/../../scripts/annotate_pdf_links.py';

        $command = escapeshellcmd("python3 $pythonScriptPath " .
            escapeshellarg($inputPdf) . ' ' .
            escapeshellarg($outputPdf) . ' ' .
            escapeshellarg($clickoutsJsonPath)
        );

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("PDF annotation failed: " . implode("\n", $output));
        }

        unlink($clickoutsJsonPath);
    }
}
