<?php

/**
 * Class Test_Framework_Numbers_Test
 */
class Test_Framework_Numbers_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet das Säubern von Preisen
     */
    public function testPrices()
    {
        $sNumbers = new Marktjagd_Service_Text_Numbers();
        $price1 = $sNumbers->normalizePrice('ab 10.00');

        $this->assertEquals(
            'ab 10,00',
            $price1
        );

        $price2 = $sNumbers->normalizePrice('pro Sack ab 10.00');
        $this->assertEquals(
            'ab 10,00',
            $price2
        );

        $price3 = $sNumbers->normalizePrice('blabbab 10.00');
        $this->assertEquals(
            '10,00',
            $price3
        );

        $price4 = $sNumbers->normalizePrice('10,00€');
        $this->assertEquals(
            '10,00',
            $price4
        );

        $price5 = $sNumbers->normalizePrice('10.000.000.000');
        $this->assertEquals(
            '10000000000,00',
            $price5
        );

        $price6 = $sNumbers->normalizePrice('10.000.000.000.00');
        $this->assertEquals(
            '10000000000,00',
            $price6
        );

        $price7 = $sNumbers->normalizePrice('10€');
        $this->assertEquals(
            '10,00',
            $price7
        );
    }
}