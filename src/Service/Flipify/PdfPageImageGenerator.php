<?php

namespace App\Service\Flipify;

use Imagick;
use ImagickException;
use RuntimeException;
use Throwable;

final class PdfPageImageGenerator
{
    public function __construct(
        private readonly int $resolution = 220,
        private readonly int $compressionQuality = 90,
    )
    {
    }

    public function generate(string $pdfPath): PdfImageSet
    {
        if (!extension_loaded('imagick') || !class_exists(Imagick::class)) {
            throw new RuntimeException('The Imagick PHP extension is required to analyse brochure PDFs.');
        }

        if (!is_file($pdfPath)) {
            throw new RuntimeException(sprintf('The PDF file "%s" could not be found.', $pdfPath));
        }

        $baseTempDirectory = sprintf('%s/flipify', sys_get_temp_dir());
        if (!is_dir($baseTempDirectory) && !mkdir($baseTempDirectory, 0775, true) && !is_dir($baseTempDirectory)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory at "%s".', $baseTempDirectory));
        }

        $tempDirectory = sprintf('%s/%s', $baseTempDirectory, uniqid('pdf_', true));
        if (!mkdir($tempDirectory, 0775) && !is_dir($tempDirectory)) {
            throw new RuntimeException(sprintf('Unable to prepare working directory "%s".', $tempDirectory));
        }

        try {
            $pageCount = $this->detectPageCount($pdfPath);
        } catch (ImagickException $exception) {
            throw new RuntimeException('Failed to read the brochure PDF: ' . $exception->getMessage(), 0, $exception);
        }

        if ($pageCount === 0) {
            throw new RuntimeException('No pages were found in the provided PDF.');
        }

        $paths = [];
        try {
            for ($index = 0; $index < $pageCount; ++$index) {
                $paths[] = $this->renderPage($pdfPath, $index, $tempDirectory);
            }
        } catch (Throwable $exception) {
            $this->cleanupDirectory($tempDirectory);

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Failed to convert a PDF page into an image: ' . $exception->getMessage(), 0, $exception);
        }

        if ($paths === []) {
            throw new RuntimeException('No pages were found in the provided PDF.');
        }

        return new PdfImageSet($tempDirectory, $paths);
    }

    private function detectPageCount(string $pdfPath): int
    {
        $imagick = new Imagick();

        try {
            $imagick->pingImage($pdfPath);

            return (int) max(0, $imagick->getNumberImages());
        } finally {
            $imagick->clear();
            $imagick->destroy();
        }
    }

    private function renderPage(string $pdfPath, int $index, string $tempDirectory): string
    {
        $page = new Imagick();
        $page->setResolution($this->resolution, $this->resolution);

        try {
            $page->readImage(sprintf('%s[%d]', $pdfPath, $index));
        } catch (ImagickException $exception) {
            throw new RuntimeException('Failed to read a page from the PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $page->setImageFormat('jpeg');
        $page->setImageBackgroundColor('white');

        if (method_exists($page, 'setImageCompressionQuality')) {
            $page->setImageCompressionQuality($this->compressionQuality);
        }

        if (method_exists($page, 'stripImage')) {
            $page->stripImage();
        }

        if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
            $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        }

        if (method_exists($page, 'mergeImageLayers')) {
            try {
                $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            } catch (ImagickException $exception) {
                $page->clear();
                $page->destroy();
                throw new RuntimeException('Failed to flatten a PDF page: ' . $exception->getMessage(), 0, $exception);
            }
        }

        $filePath = sprintf('%s/page-%03d.jpg', $tempDirectory, $index + 1);

        try {
            $page->writeImage($filePath);
        } catch (ImagickException $exception) {
            $page->clear();
            $page->destroy();
            throw new RuntimeException('Failed to convert a PDF page into an image: ' . $exception->getMessage(), 0, $exception);
        }

        $page->clear();
        $page->destroy();

        return $filePath;
    }

    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $paths = glob($directory . '/*');
        if ($paths !== false) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        @rmdir($directory);
    }
}
