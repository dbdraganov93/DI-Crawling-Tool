<?php

namespace Marktjagd\Service\DateNormalization\Formats;

class FormatSlashYMD extends AbstractFormat implements DateNormalizationFormat {
    protected const SUPPORTED_FORMATS = [
        'Y/m/d',
        'Y/m/d H:i:s',
    ];
    protected const REGEX_PATTERN = '#^(?<year>\d{2,4})\/(?<month>\d{1,2})\/(?<day>\d{1,2})*(?: *(?<hour>\d{1,2})?(?:\:(?<minute>\d{1,2})?(?:\:(?<second>\d{1,2}))*)*)*$#';
    protected const PRINT_FORMAT_DATE = '%s/%s/%s';
    protected const PRINT_FORMAT_TIME = '%s:%s:%s';
    protected const DATE_PARTS_ORDER = ['year', 'month', 'day', 'hour', 'minute', 'second'];

    public function __construct(string $format)
    {
        parent::__construct($format);

        $this->regexPattern = self::REGEX_PATTERN;
        $this->datePartsOrder = self::DATE_PARTS_ORDER;
        $this->printFormat = self::PRINT_FORMAT_DATE . ($this->isTimeIncluded ? ' ' . self::PRINT_FORMAT_TIME : '');
    }

    public static function supports(string $dateFormat): bool
    {
        return in_array($dateFormat, self::SUPPORTED_FORMATS);
    }
}
