<?php
namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PdfLinkAnnotatorService
{
    private string $pythonScript;

    public function __construct()
    {
        $this->pythonScript = __DIR__ . '/../../scripts/add_links.py';
    }
    public function annotate(string $pdfPath, string $outputPath, array $clickouts): void
    {
        $clickoutsJsonPath = tempnam(sys_get_temp_dir(), 'clickouts_') . '.json';
        file_put_contents($clickoutsJsonPath, json_encode($clickouts));

        $tempOutput = tempnam(sys_get_temp_dir(), 'pdf_output_') . '.pdf';

        $process = new Process([
            'python3',
            $this->pythonScript,
            $pdfPath,
            $clickoutsJsonPath,
            $tempOutput
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Overwrite the original with the modified version
        copy($tempOutput, $outputPath);
        @unlink($clickoutsJsonPath);
        @unlink($tempOutput);
    }

}
