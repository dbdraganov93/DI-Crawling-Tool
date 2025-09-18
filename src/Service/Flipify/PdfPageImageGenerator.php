<?php

namespace App\Service\Flipify;

use Imagick;
use ImagickException;
use RuntimeException;

final class PdfPageImageGenerator
{
    public function __construct(private readonly int $resolution = 220)
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

        $imagick = new Imagick();
        $imagick->setResolution($this->resolution, $this->resolution);

        try {
            $imagick->readImage($pdfPath);
        } catch (ImagickException $exception) {
            throw new RuntimeException('Failed to read the brochure PDF: ' . $exception->getMessage(), 0, $exception);
        }

        $paths = [];
        foreach ($imagick as $index => $page) {
            \assert($page instanceof Imagick);
            $page->setImageFormat('png');
            $page->setImageBackgroundColor('white');
            if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
                $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }
            if (method_exists($page, 'mergeImageLayers')) {
                $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }

            $filePath = sprintf('%s/page-%03d.png', $tempDirectory, $index + 1);

            try {
                $page->writeImage($filePath);
            } catch (ImagickException $exception) {
                $imagick->clear();
                $imagick->destroy();
                throw new RuntimeException('Failed to convert a PDF page into an image: ' . $exception->getMessage(), 0, $exception);
            }

            $paths[] = $filePath;
        }

        $imagick->clear();
        $imagick->destroy();

        if ($paths === []) {
            throw new RuntimeException('No pages were found in the provided PDF.');
        }

        return new PdfImageSet($tempDirectory, $paths);
    }
}
