<?php

namespace Marktjagd\Service\DateNormalization\Formats;

class FormatDotDMY extends AbstractFormat implements DateNormalizationFormat
{
    protected const SUPPORTED_FORMATS = [
        'd.m.Y',
        'd.m.Y H:i:s',
    ];
    protected const REGEX_PATTERN = '#^(?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{2,4})*(?: *(?<hour>\d{1,2})?(?:\:(?<minute>\d{1,2})?(?:\:(?<second>\d{1,2}))*)*)*$#';
    protected const PRINT_FORMAT_DATE = '%s.%s.%s';
    protected const PRINT_FORMAT_TIME = '%s:%s:%s';

    public function __construct(string $format)
    {
        parent::__construct($format);

        $this->regexPattern = self::REGEX_PATTERN;
        $this->printFormat = self::PRINT_FORMAT_DATE . ($this->isTimeIncluded ? ' ' . self::PRINT_FORMAT_TIME : '');
    }

    public static function supports(string $dateFormat): bool
    {
        return in_array($dateFormat, self::SUPPORTED_FORMATS);
    }
}
