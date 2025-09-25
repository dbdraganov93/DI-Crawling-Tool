<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\DiscoverBrochure;
use PHPUnit\Framework\TestCase;

class DiscoverBrochureTest extends TestCase
{
    public function testFromArrayWithSnakeCaseKeys(): void
    {
        $input = [
            'brochure_number' => 'ernstings_discover_Kids_02.2025_00-1027',
            'type' => 'discover',
            'url' => '/api/brochure_pages/1441591',
            'title' => "Ernstings: Ernsting's family: Allround-Outfits",
            'tags' => 'fashion,kids',
            'start' => '2025-02-06T00:00:00+01:00',
            'end' => '2025-02-15T23:59:59+01:00',
            'visible_start' => '2025-02-06T00:00:00+01:00',
            'store_number' => '00-1027',
            'distribution' => '/api/sales_regions/67553',
            'variety' => 'leaflet',
            'national' => 0,
            'gender' => '',
            'age_range' => '',
            'tracking_bug' => 'https://tracking.example.com',
            'options' => ['version' => '2021-04-19'],
            'lang_code' => 'de',
            'zipcode' => '',
            'layout' => '{}',
        ];

        $brochure = DiscoverBrochure::fromArray($input);

        self::assertSame($input['brochure_number'], $brochure->getBrochureNumber());
        self::assertSame($input['type'], $brochure->getType());
        self::assertSame($input['url'], $brochure->getPdfUrl());
        self::assertSame($input['distribution'], $brochure->getDistribution());
        self::assertSame($input['tracking_bug'], $brochure->getTrackingBug());
        self::assertSame($input['options'], $brochure->getOptions());

        $expected = $input;
        $expected['options'] = $input['options'];

        self::assertSame($expected, $brochure->toArray());
    }

    public function testOptionsAcceptsJsonString(): void
    {
        $payload = [
            'brochure_number' => 'discover_123',
            'options' => json_encode(['dpi' => 250, 'cutPages' => true], JSON_THROW_ON_ERROR),
        ];

        $brochure = DiscoverBrochure::fromArray($payload);

        self::assertSame(
            ['dpi' => 250, 'cutPages' => true],
            $brochure->getOptions()
        );
    }
}
