<?php
declare(strict_types=1);

namespace App\Service;

class ClickoutsMapperService
{
    public function formatClickoutsForShopfully(array $data): array
    {
        $clickouts = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $clickout) {
                $flyer = $clickout['FlyerGib'] ?? [];

                $url = $flyer['external_url']
                    ?? ($flyer['settings']['layout']['button'][0]['attributes']['href'] ?? null)
                    ?? ($flyer['settings']['layout']['buttons'][0]['attributes']['href'] ?? null);

                if (!$url) {
                    continue;
                }

                $clickouts[] = [
                    'url' => $url,
                    'pageNumber' => $flyer['settings']['flyer_page'] ?? null,
                    'width' => $flyer['settings']['pin']['shape']['width'] ?? null,
                    'height' => $flyer['settings']['pin']['shape']['height'] ?? null,
                    'x' => $flyer['settings']['pin']['shape']['x'] ?? null,
                    'y' => $flyer['settings']['pin']['shape']['y'] ?? null,
                ];
            }
        }


        return $clickouts; // вече са с правилна структура за Python
    }
}
