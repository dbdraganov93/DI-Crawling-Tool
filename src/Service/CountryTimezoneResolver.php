<?php

namespace App\Service;

class CountryTimezoneResolver
{
    public function getTimezoneForCountry(string $countryCode): ?string
    {
        $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($countryCode));

        return $timezones[0] ?? null;
    }

    public function resolveFromApiPath(string $countryApiPath): ?string
    {
        if (preg_match('#/api/countries/([A-Z]{2})#', $countryApiPath, $matches)) {
            return $this->getTimezoneForCountry($matches[1]);
        }

        return null;
    }
}
