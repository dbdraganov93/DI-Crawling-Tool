<?php

class Test_Framework_Openings_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet die Ermittlung der ÖZ mit Tabelle
     * Bsp.: reifen.com
     */
    public function testOpeningsTable()
    {
        $htmlString = '<table width="90%" cellspacing="0" cellpadding="0" border="0" id="tblFilOpen">
            <tbody>

                <tr>
                    <td>
                        Mo.-Fr.:
                    </td>
                    <td>
                        09:00 bis 18:30
                    </td>
                </tr>

                <tr>
                    <td>
                        Sa.:
                    </td>
                    <td>
                        09:00 bis 14:00
                    </td>
                </tr>

            </tbody>
        </table>';

        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'table');

        $this->assertEquals(
            'Mo 09:00-18:30, Di 09:00-18:30, Mi 09:00-18:30, Do 09:00-18:30, Fr 09:00-18:30, Sa 09:00-14:00',
            $openings
        );
    }

    /**
     * Testet die Ermittlung von ÖZ mit Text
     * Bsp.: idee der Kreativmarkt
     */
    public function testOpeningsText1()
    {
        $htmlString = '<p class="right w-160">
                        <strong>&Ouml;ffnungszeiten:</strong><br />
                        Mo-Sa 10.00-20.00 Uhr<br />
                        <br />Verkaufsoffene Sonntage:<br />
                        08.09.2013<br />
                        22.09.2013<br />
                        20.10.2013<br />
                        03.11.2013<br />
                        08.12.2013<br />
                        22.12.2013<br />
                        jeweils von 13.00-18.00 Uhr<br/>
                        <br/>';

        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 10:00-20:00, Di 10:00-20:00, Mi 10:00-20:00, Do 10:00-20:00, Fr 10:00-20:00, Sa 10:00-20:00',
            $openings
        );
    }

    /**
     * Testet die Ermittlung von ÖZ mit Text mit großgeschriebenen Wochentagen
     * Bsp.: Pfennigpfeiffer
     */
    public function testOpeningsText2()
    {
        $htmlString = 'MO: 09:00 - 18:30,DI: 09:00 - 18:30,MI: 09:00 - 18:30,DO: 09:00 - 18:30,FR: 09:00 - 18:30,SA: 08:00 - 13:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 09:00-18:30, Di 09:00-18:30, Mi 09:00-18:30, Do 09:00-18:30, Fr 09:00-18:30, Sa 08:00-13:00',
            $openings
        );
    }

    /**
     * Testet die Ermittlung von ÖZ mit ausgeschriebenen Wochentagen
     * Bsp.: Küche & Co
     */
    public function testOpeningsText3()
    {
        $htmlString = 'Wochentags: 10.00 Uhr - 18.00 Uhr, Samstags: 10.00 Uhr - 14.00 Uhr';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 10:00-18:00, Di 10:00-18:00, Mi 10:00-18:00, Do 10:00-18:00, Fr 10:00-18:00, Sa 10:00-14:00',
            $openings
        );

        $htmlString = 'Montag-Freitags 10.00 Uhr - 18.00 Uhr, Samstags: 10.00 Uhr - 14.00 Uhr';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 10:00-18:00, Di 10:00-18:00, Mi 10:00-18:00, Do 10:00-18:00, Fr 10:00-18:00, Sa 10:00-14:00',
            $openings
        );
    }

    /**
     * Testet Uhrzeiten ohne Doppelpunkte
     * Bsp.: Ralph Lauren
     */
    public function testOpeningsText4()
    {
        $htmlString = 'Mo-Do: 9.30-8; Fr: 9-8; Sa: 8.30-14';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 9:30-24:00, Di 00:00-8:00, Di 9:30-24:00, Mi 00:00-8:00, Mi 9:30-24:00, '
            . 'Do 00:00-8:00, Do 9:30-24:00, Fr 00:00-8:00, Fr 9:00-24:00, Sa 00:00-8:00, Sa 8:30-14:00',
            $openings);
    }

    /**
     * Testet "und"-verknüpfte Uhrzeiten
     * Bsp.: Amplifon
     */
    public function testOpeningText5()
    {
        $htmlString = 'Montag - Freitag 09:00 - 13:00 und 14:00 - 18:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 09:00-13:00, Mo 14:00-18:00, Di 09:00-13:00, Di 14:00-18:00, '
                . 'Mi 09:00-13:00, Mi 14:00-18:00, Do 09:00-13:00, Do 14:00-18:00, '
                . 'Fr 09:00-13:00, Fr 14:00-18:00',
            $openings
        );

        $htmlString = 'Di. 09:00 - 12:00 &amp; 13:00 - 17:00<br>Do. 10:00 - 15:00';
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Di 09:00-12:00, Di 13:00-17:00, Do 10:00-15:00',
            $openings
        );
    }

    /**
     * Testet komma- bzw. und-getrennte Wochentage
     * Bsp.: Amplifon
     */
    public function testOpeningText6()
    {
        $sOpenings = new Marktjagd_Service_Text_Times();

        $htmlString = 'Montag, Donnerstag 08:00 - 17:00 Dienstag 08:00 - 18:00 Mittwoch, Freitag 08:00 - 14:00';
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 08:00-17:00, Di 08:00-18:00, Mi 08:00-14:00, Do 08:00-17:00, Fr 08:00-14:00',
            $openings
        );

        $htmlString = 'Montag, Dienstag, Donnerstag, Freitag 09:00 - 13:00 und14:00 - 18:00 Mi. 09:00 - 13:00';
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 09:00-13:00, Mo 14:00-18:00, Di 09:00-13:00, Di 14:00-18:00, Mi 09:00-13:00, '
                . 'Do 09:00-13:00, Do 14:00-18:00, Fr 09:00-13:00, Fr 14:00-18:00',
            $openings
        );

        $htmlString = 'Montag & Freitag: 10:00-12:00, Dienstag+Mittwoch 10:00-16:00, Sa u. So 10:00-11:00';
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 10:00-12:00, Di 10:00-16:00, Mi 10:00-16:00, Fr 10:00-12:00, Sa 10:00-11:00, So 10:00-11:00',
            $openings
        );
    }

    /**
     * Testet das Überschreiben von Wochentagen
     */
    public function testOpeningText7()
    {
        $htmlString = 'Montag - Freitag 08:00-19:00, Dienstag 08:00-12:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');
        $this->assertEquals(
            'Mo 08:00-19:00, Di 08:00-12:00, Mi 08:00-19:00, Do 08:00-19:00, Fr 08:00-19:00',
            $openings
        );
    }

    /**
     * Testet Öffnungszeiten (getrennt durch br)
     * Bsp: Huster
     */
    public function testOpeningText8()
    {
        $htmlString = 'Montag bis Freitag<br />8.00 bis 18.30 Uhr<br />Samstag<br />8.00 bis 13.00 Uhr';
        $htmlString2 = '<div>Montag bis Freitag 8.00 bis 18.30 Uhr</div><div>Samstag 8.00 bis 13.00 Uhr</div>';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text');

        $this->assertEquals(
            'Mo 8:00-18:30, Di 8:00-18:30, Mi 8:00-18:30, Do 8:00-18:30, Fr 8:00-18:30, Sa 8:00-13:00',
            $openings
        );

        $openings = $sOpenings->generateMjOpenings($htmlString2, 'text');

        $this->assertEquals(
            'Mo 8:00-18:30, Di 8:00-18:30, Mi 8:00-18:30, Do 8:00-18:30, Fr 8:00-18:30, Sa 8:00-13:00',
            $openings
        );
    }

    /**
     * Testen des Anhängen von Wochentagen + Uhrzeiten
     * Bsp.: EdekaNST Storecrawler
     */
    public function testOpeningText9()
    {
        $htmlString = 'Mo 7:00 - 12:30, Mo 14:00 - 18:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString);
        $this->assertEquals('Mo 7:00-12:30, Mo 14:00-18:00', $openings);

    }

    /**
     * Test zum Aufteilen der Öffnungszeiten auf zwei Tage (über 24:00 Uhr)
     * Bsp.: Mc Donalds
     */
    public function testOpeningText10()
    {
        $htmlString = 'Mo 7:00 - 02:00, Di 08:00 - 18:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('Mo 7:00-24:00, Di 00:00-02:00, Di 08:00-18:00', $openings);
    }

    /**
     * Test von Bereinigung englischer Wochentage
     * Bsp.: K&L Ruppert
     */
    public function testOpeningText11()
    {
        $htmlString = 'Tu 7:00 - 19:00, Th 08:00 - 18:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('Di 7:00-19:00, Do 08:00-18:00', $openings);

        $htmlString = 'Tuesday 7:00 - 19:00, Thursday 08:00 - 18:00';
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('Di 7:00-19:00, Do 08:00-18:00', $openings);
    }
    
    /**
     * Test zum Entfernen von Feiertagen
     * Bsp.: Bäckerei Middelberg
     */
    public function testOpeningText12()
    {
        $htmlString = 'Sonntag 7:00 - 19:00, Feiertag 08:00 - 18:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('So 7:00-19:00', $openings);
        
        $htmlString = 'Sonn- und Feiertag 7:00 - 19:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('So 7:00-19:00', $openings);
    }

    /**
     * Test von wochenübergreifenden Wochentags-Ranges
     */
    public function testOpeningText13()
    {
        $htmlString = 'So-Di 7:00 - 19:00, Do-Sa 08:00-15:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('Mo 7:00-19:00, Di 7:00-19:00, Do 08:00-15:00, Fr 08:00-15:00, Sa 08:00-15:00, So 7:00-19:00', $openings);

        $htmlString = 'Sa-Di 10:00 - 12:00, 14:00-19:00, Do 09:00-12:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);
        $this->assertEquals('Mo 10:00-12:00, Mo 14:00-19:00, Di 10:00-12:00, Di 14:00-19:00, Do 09:00-12:00, Sa 10:00-12:00, Sa 14:00-19:00, So 10:00-12:00, So 14:00-19:00', $openings);
    }

    public function testOpeningText14()
    {
        $htmlString = ' Mo. - Sa. : 10:00 - 20:00 ';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);

        $this->assertEquals(
            'Mo 10:00-20:00, Di 10:00-20:00, Mi 10:00-20:00, Do 10:00-20:00, Fr 10:00-20:00, Sa 10:00-20:00',
            $openings
        );
    }

    public function testOpeningText15()
    {
        $htmlString = 'Sa - Mo 0900 - 1600, Do 1000 - 1730';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);

        $this->assertEquals(
            'Mo 09:00-16:00, Do 10:00-17:30, Sa 09:00-16:00, So 09:00-16:00',
            $openings
        );
    }

    public function testOpeningText16()
    {
        $htmlString = 'Di. 09:00 - 12:00 &amp; 13:00 - 17:00<br>Do. 10:00 - 15:00';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);

        $this->assertEquals(
            'Di 09:00-12:00, Di 13:00-17:00, Do 10:00-15:00',
            $openings
        );
    }

    public function testOpeningText17()
    {
        $htmlString = 'Mo-Fr 10 am - 5 pm';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $openings = $sOpenings->generateMjOpenings($htmlString, 'text', true);

        $this->assertEquals(
            'Mo 10:00-17:00, Di 10:00-17:00, Mi 10:00-17:00, Do 10:00-17:00, Fr 10:00-17:00',
            $openings
        );
    }
}