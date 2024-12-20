<?php

namespace App\Service;

use setasign\Fpdi\Tcpdf\Fpdi; // Correct FPDI class

class ClickoutPdfService
{
    private $pdf;

    public function __construct()
    {
        $this->pdf = new Fpdi(); // Use FPDI's wrapper around TCPDF
        $this->pdf->SetCreator('Symfony Clickout Service');
        $this->pdf->SetAuthor('Your Name');
        $this->pdf->SetTitle('PDF with Clickable Links');
        $this->pdf->SetMargins(10, 10, 10);
    }

    /**
     * Adds a clickable link in the center of an existing PDF.
     *
     * @param string $pdfPath Path to the existing PDF file.
     * @param string $url     URL for the clickout link.
     */
    public function addClickoutToPdf(string $pdfPath, string $url): void
    {
        // Load the existing PDF
        $pageCount = $this->pdf->setSourceFile($pdfPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import the current page
            $templateId = $this->pdf->importPage($pageNo);
            $size = $this->pdf->getTemplateSize($templateId);

            // Add a page and use the imported template
            $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $this->pdf->useTemplate($templateId);

            // Calculate center position for the clickable area
            $pageWidth = $size['width'];
            $pageHeight = $size['height'];
            $linkWidth = 80;  // Width of the clickable area
            $linkHeight = 20; // Height of the clickable area

            $x = ($pageWidth - $linkWidth) / 2;
            $y = ($pageHeight - $linkHeight) / 2;

            // Add a clickable link
            $this->pdf->SetXY($x, $y);
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetTextColor(0, 0, 255);
            $this->pdf->Cell($linkWidth, $linkHeight, 'Click Here', 0, 1, 'C', false, $url);
        }

        // Save the modified PDF, overwriting the original
        $this->pdf->Output($pdfPath, 'F');
    }
}
