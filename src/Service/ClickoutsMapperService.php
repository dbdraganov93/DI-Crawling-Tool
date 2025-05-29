<?php
namespace App\Service;

class ClickoutsMapperService {
    public function formatClickoutsForShopfully(array $data): array
    {
        $clickouts = [];
        foreach ($data['data'] as $k => $clickout) {
            $clickouts[$k]['url'] = $clickout['FlyerGib']['external_url'] ?? $clickout['FlyerGib']['settings']['layout']['button'][0]['attributes']['href'] ?? $clickout['FlyerGib']['settings']['layout']['buttons'][0]['attributes']['href'];
            $clickouts[$k]['pageNumber'] = $clickout['FlyerGib']['settings']['flyer_page'];
            $clickouts[$k]['width'] = $clickout['FlyerGib']['settings']['pin']['shape']['width'];
            $clickouts[$k]['height'] = $clickout['FlyerGib']['settings']['pin']['shape']['height'];
            $clickouts[$k]['x'] = $clickout['FlyerGib']['settings']['pin']['shape']['x'];
            $clickouts[$k]['y'] = $clickout['FlyerGib']['settings']['pin']['shape']['y'];
        }

        return $clickouts;
    }
}