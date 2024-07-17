<?php

namespace Marktjagd\Service\DateNormalization\Formats;

interface DateNormalizationFormat {
    static function supports(string $dateFormat): bool;
    function normalize(string $dateString): string;
}
