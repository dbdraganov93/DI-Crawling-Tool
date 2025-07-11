<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;

class PdfLinkAnnotatorService
{
    private string $pythonScript;

    public function __construct()
    {
        $this->pythonScript = __DIR__ . '/../../scripts/add_links.py';
    }
    public function annotate(string $pdfPath, string $outputPath, array $clickouts): void
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException('Input PDF not found: ' . $pdfPath);
        }

        if (!file_exists($this->pythonScript)) {
            throw new \RuntimeException('Annotation script missing at ' . $this->pythonScript);
        }

        if (empty($clickouts)) {
            // Nothing to annotate, just copy the file so callers always have
            // the expected output available
            copy($pdfPath, $outputPath);
            return;
        }

        // Ensure PyMuPDF is available before running the Python script so we
        // can give a helpful error message instead of a cryptic stack trace.
        $check = new Process([
            'python3',
            '-c',
            'import importlib.util,sys; sys.exit(0 if importlib.util.find_spec("fitz") else 1)'
        ]);
        $check->run();
        if (!$check->isSuccessful()) {
            throw new \RuntimeException('PyMuPDF (fitz) is not installed. Run `pip install PyMuPDF`.');
        }

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
            $message = trim($process->getErrorOutput() . '\n' . $process->getOutput());
            if ($message === '') {
                $message = 'Unknown error while running add_links.py';
            }

            throw new \RuntimeException('PDF annotation failed: ' . $message);
        }

        // Overwrite the original with the modified version
        copy($tempOutput, $outputPath);
        @unlink($clickoutsJsonPath);
        @unlink($tempOutput);
    }
}
