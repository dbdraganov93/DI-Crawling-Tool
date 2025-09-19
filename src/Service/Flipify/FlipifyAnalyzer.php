<?php

namespace App\Service\Flipify;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FlipifyAnalyzer
{
    public function __construct(
        private readonly PdfPageImageGenerator $imageGenerator,
        private readonly OpenAiProductExtractor $productExtractor,
        #[Autowire(service: 'monolog.logger.flipify')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function analyze(string $pdfPath): array
    {
        $this->logger->debug('Generating Flipify page previews.', [
            'pdfPath' => $pdfPath,
        ]);

        $imageSet = $this->imageGenerator->generate($pdfPath);
        $pageCount = \count($imageSet->getPaths());

        $this->logger->info('Generated brochure image set.', [
            'pdfPath' => $pdfPath,
            'pageCount' => $pageCount,
        ]);

        try {
            if ($imageSet->isEmpty()) {
                $this->logger->warning('Flipify analyser produced no images for brochure.', ['pdf' => $pdfPath]);

                return [];
            }

            $products = $this->productExtractor->extractFromImages($imageSet);

            if ($products === []) {
                $this->logger->info('Flipify analysis completed with no products detected.', [
                    'pdfPath' => $pdfPath,
                ]);
            }

            return $products;
        } finally {
            $imageSet->cleanup();
        }
    }
}
