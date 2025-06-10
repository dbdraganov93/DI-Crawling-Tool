<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\LocaleExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use App\Service\LocaleService;
class LocaleExtension extends AbstractExtension
{
    private LocaleService $localeService;

    public function __construct(LocaleService $localeService)
    {
        $this->localeService = $localeService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_owner_id_by_locale', [$this->localeService, 'getOwnerId']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('filter_name', [LocaleExtensionRuntime::class, 'doSomething']),
        ];
    }

}
