<?php

namespace Marktjagd\Service\DateNormalization;

use Exception;
use Marktjagd\Service\DateNormalization\Formats\DateNormalizationFormat;
use Marktjagd\Service\DateNormalization\Formats\FormatDotDMY;
use Marktjagd\Service\DateNormalization\Formats\FormatDashDMY;
use Marktjagd\Service\DateNormalization\Formats\FormatSlashYMD;
use Marktjagd\Service\DateNormalization\Formats\FromatDashYMD;
use Marktjagd\Service\DateNormalization\Formats\FormatDMY;

class FormatFactory {
    private static array $dateFormats = [
        FormatDotDMY::class,
        FormatDashDMY::class,
        FormatSlashYMD::class,
        FromatDashYMD::class,
        FormatDMY::class,
        // Add more date format classes here
    ];

    public static function create(string $dateFormat): DateNormalizationFormat
    {
        foreach (self::$dateFormats as $dateFormatClass) {
            if ($dateFormatClass::supports($dateFormat)) {
                return new $dateFormatClass($dateFormat);
            }
        }

        throw new Exception("Unsupported date format");
    }
}
