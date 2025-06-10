<?php
namespace App\Service;

class LocaleService
{
    const LOCALE = [
       231  => 'it_it',
       239  => 'es_es',
       241  => 'fr_fr',
       242  => 'pt_pt',
       244  => 'en_au',
    ];

    public function getLocale($ownerId): string
    {
        return self::LOCALE[$ownerId] ?? '';
    }

    public function getOwnerId(string $locale): ?int
    {
        return array_search($locale, self::LOCALE, true) ?: null;
    }
}