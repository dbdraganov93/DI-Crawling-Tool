<?php

namespace App\Service\Flipify;

use Psr\Log\LoggerInterface;

final class FlipifyAnalyzer
{
    public function __construct(
        private readonly PdfPageImageGenerator $imageGenerator,
        private readonly OpenAiProductExtractor $productExtractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function analyze(string $pdfPath): array
    {
        $imageSet = $this->imageGenerator->generate($pdfPath);

        try {
            if ($imageSet->isEmpty()) {
                $this->logger->warning('Flipify analyser produced no images for brochure.', ['pdf' => $pdfPath]);

                return [];
            }

            return $this->productExtractor->extractFromImages($imageSet);
        } finally {
            $imageSet->cleanup();
        }
    }
}
