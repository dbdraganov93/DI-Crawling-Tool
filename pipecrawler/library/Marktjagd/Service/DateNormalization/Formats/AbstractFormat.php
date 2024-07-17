<?php

namespace Marktjagd\Service\DateNormalization\Formats;

use DateTime;
use Exception;

abstract class AbstractFormat {
    protected const DEFAULT_DATE_FORMAT = 'd.m.Y';
    protected const DEFAULT_TIME_FORMAT = 'H:i:s';
    protected const DATE_PARTS = ['day', 'month', 'year'];
    protected const TIME_PARTS = ['hour', 'minute', 'second'];
    protected const FULL_YEAR = 4;
    protected const SHORT_YEAR = 2;
    protected const SEPTEMBER = '09';
    protected const JANUARY = '01';
    protected const FEBRUARY = '02';

    protected string $regexPattern = '##';
    protected string $format = '';
    protected string $outputFormat = '';
    protected array $datePartsOrder = [];
    protected bool $isTimeIncluded = false;
    protected string $printFormat = '';
    protected array $cachedDates = [];
    protected bool $isEndDate = false;

    public function __construct(string $format)
    {
        $this->format = $format;
        $this->isTimeIncluded = false !== strpos($format, 'H:i:s');
        $this->outputFormat = self::DEFAULT_DATE_FORMAT . ($this->isTimeIncluded ? ' ' . self::DEFAULT_TIME_FORMAT : '');
    }

    public function normalize(string $dateString): string
    {
        if (!isset($this->cachedDates[$dateString])) {
            $date = DateTime::createFromFormat($this->format, $dateString);
            if (!$date || $date->format($this->format) !== $dateString) {
                $date = $this->fixDate($dateString);
            }

            if (!$date) {
                throw new Exception('The date "' . $dateString . '" could not be normalized.
                Please make sure the date matches the expected format.
                Format supplied: "' . $this->format . '".');
            }

            $this->cachedDates[$dateString] = $date->format($this->outputFormat);
        }

        return $this->cachedDates[$dateString];
    }

    /**
     * @return DateTime|false
     */
    public function fixDate(string $dateString)
    {
        $parsedDate = $this->parseDate($dateString);
        $fixedDate = $this->formatDate($parsedDate);

        return DateTime::createFromFormat($this->format, $fixedDate);
    }

    protected function parseDate(string $dateString): array
    {
        if (preg_match($this->regexPattern, $dateString, $matches)) {
            $parsedDate = [];
            $parts = $this->getDatePartsOrder();

            foreach ($parts as $part) {
                $value = $matches[$part] ?? '';

                if ('year' === $part) {
                    $value = $this->fixYear($value, $this->fixDatePart($matches['month']));
                }
                else {
                    $value = $this->fixDatePart($value);
                }

                $parsedDate[$part] = $value;
            }

            return $parsedDate;
        }

        return [];
    }

    protected function fixDatePart(string $string, string $padString = '0', int $padType = STR_PAD_LEFT): string
    {
        return str_pad($string, 2, $padString, $padType);
    }

    protected function fixYear(string $year, string $month): string
    {
        if (!$year || (!self::isShortYear($year) && !self::isFullYear($year))) {
            $yearToSet = date('Y');
            $currentMonth = date('m');

            if ($this->isEndDate) {
                if ($month < $currentMonth) {
                    $yearToSet++;
                }
            }

            // check if date is for next year when we are in the last 3 months of the year
            // otherwise if we just use $currentMonth > $month
            // we will get wrong year in case of a start date that is in the previous month
            // i.e. current month is 04 (April) and we get a date for 03 (March)
            else if (self::SEPTEMBER < $currentMonth && in_array($month, [self::JANUARY, self::FEBRUARY])) {
                // it is very unlikely that we will receive a brochure which is 3+ months prior to the current date
                $yearToSet++;
            }

            return $yearToSet;
        }

        if (self::isShortYear($year)) {
            return  '20' . $year;
        }

        return $year;
    }

    protected function formatDate(array $dateParts): string
    {
        return sprintf($this->printFormat, ...array_values($dateParts));
    }

    protected function getDatePartsOrder(): array
    {
        if (empty($this->datePartsOrder)) {
            $this->datePartsOrder = $this->isTimeIncluded ? array_merge(self::DATE_PARTS, self::TIME_PARTS) : self::DATE_PARTS;
        }

        return $this->datePartsOrder;
    }

    public function setIsEndDate(bool $isEndDate): void
    {
        $this->isEndDate = $isEndDate;
    }

    protected static function isShortYear(string $year): bool
    {
        return self::SHORT_YEAR === strlen($year);
    }

    protected static function isFullYear(string $year): bool
    {
        return self::FULL_YEAR === strlen($year);
    }
}
