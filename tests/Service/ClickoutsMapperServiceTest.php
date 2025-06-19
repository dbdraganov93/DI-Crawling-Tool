<?php

namespace App\Tests\Service;

use App\Service\ClickoutsMapperService;
use PHPUnit\Framework\TestCase;

class ClickoutsMapperServiceTest extends TestCase
{
    public function testFormatClickoutsForShopfullyMapsData(): void
    {
        $service = new ClickoutsMapperService();
        $input = [
            'data' => [
                [
                    'FlyerGib' => [
                        'external_url' => 'http://e.com',
                        'settings' => [
                            'flyer_page' => 2,
                            'pin' => [
                                'shape' => [
                                    'width' => 10,
                                    'height' => 20,
                                    'x' => 1,
                                    'y' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $result = $service->formatClickoutsForShopfully($input);
        $this->assertSame([
            [
                'url' => 'http://e.com',
                'pageNumber' => 2,
                'width' => 10,
                'height' => 20,
                'x' => 1,
                'y' => 2,
            ]
        ], $result);
    }

    public function testFormatClickoutsSkipsEntriesWithoutUrl(): void
    {
        $service = new ClickoutsMapperService();
        $input = ['data' => [['FlyerGib' => ['settings' => []]]]];
        $this->assertSame([], $service->formatClickoutsForShopfully($input));
    }
}
