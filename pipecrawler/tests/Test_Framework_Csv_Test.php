<?php

/**
 * Class Test_Framework_Csv_Test
 */
class Test_Framework_Csv_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet das Einlesen von ISO und UTF8 Codierten CSVs und prüft ob bei inhaltlich
     * gleichen CSVs aber unterschiedlicher Codierung, die selben Entities generiert werden
     */
    public function testCsvUtf8()
    {
        $sMjCollection = new Marktjagd_Service_Input_MarktjagdCsv();
        $cUtf8 = $sMjCollection->convertToCollection(APPLICATION_PATH . '/../tests/files/stores_utf8.csv', 'stores');
        $cIso = $sMjCollection->convertToCollection(APPLICATION_PATH . '/../tests/files/stores_iso.csv', 'stores');

        $sUtf8 = '';
        foreach ($cUtf8->getElements() as $eUtf8) {
            /* @var $eUtf8 Marktjagd_Entity_Api_Store */
            $sUtf8 .= $eUtf8->getHash(true);
        }

        $sIso = '';
        foreach ($cIso->getElements() as $eIso) {
            /* @var $eIso Marktjagd_Entity_Api_Store */
            $sIso .= $eIso->getHash(true);
        }

        $this->assertEquals(
            $sUtf8,
            $sIso
        );
    }

    /**
     * Testet, ob neue Attribute für die Brochures korrekt verarbeitet werden
     */
    public function testNationalFlag()
    {
        $sMjCollection = new Marktjagd_Service_Input_MarktjagdCsv();
        $cBrochure = $sMjCollection->convertToCollection(
            APPLICATION_PATH . '/../tests/files/brochures_national.csv',
            'brochures'
        );

        foreach ($cBrochure->getElements() as $eBrochure) {
            /* @var $eBrochure Marktjagd_Entity_Api_Brochure */
            $this->assertEquals(
                '1',
                $eBrochure->getNational()
            );

            $this->assertEquals(
                'male',
                $eBrochure->getGender()
            );

            $this->assertEquals(
                '10-50',
                $eBrochure->getAgeRange()
            );

            $this->assertEquals(
                'leaflet',
                $eBrochure->getVariety()
            );
        }
    }
}