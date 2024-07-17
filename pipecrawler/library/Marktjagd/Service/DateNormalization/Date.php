<?php

use Marktjagd\Service\DateNormalization\FormatFactory;

class Marktjagd_Service_DateNormalization_Date
{
    private const DEFAULT_FORMAT = 'd.m.Y';
    private array $normalizers = [];

    public function normalize(string $dateString, string $dateFormat = self::DEFAULT_FORMAT, bool $isEndDate = false): string
    {
        $dateString = $this->cleanDateString($dateString);
        if (!isset($this->normalizers[$dateFormat])) {
            $this->normalizers[$dateFormat] = FormatFactory::create($dateFormat);
        }

        $normalizer = $this->normalizers[$dateFormat];
        $normalizer->setIsEndDate($isEndDate);

        return $normalizer->normalize($dateString);
    }

    public function cleanDateString(string $dateString): string
    {
        return preg_replace(
            ['#\\\\?T#', '#\\\\?Z#'],
            [' ', ''],
            $dateString);
    }
}
