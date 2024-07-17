<?php

class Marktjagd_Service_Validator_PublicHoliday
{

    private const API_URL = 'https://date.nager.at/api/v3/PublicHolidays/%s/%s';
    private string $countryCode;

    public function __construct(string $countryCode = 'DE')
    {
        $this->countryCode = $countryCode;
    }

    public function getPublicHolidays(string $year = null): array
    {
        if (!$year) {
            $year = date('Y');
        }

        return json_decode(file_get_contents(sprintf(self::API_URL, $year, $this->countryCode)), true);
    }

    public function isPublicHoliday(string $date): bool
    {
        $date = new DateTime($date);
        $year = $date->format('Y');
        $date = $date->format('Y-m-d');

        $holidays = $this->getPublicHolidays($year);

        $isHoliday = array_filter($holidays, function($holiday) use ($date) {
            return $date == $holiday['date'] && true == $holiday['global'];
        });

        return !empty($isHoliday);
    }
}
