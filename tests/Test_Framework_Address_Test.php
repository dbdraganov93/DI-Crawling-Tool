<?php

/**
 * Class Test_Framework_Address_Test
 */
class Test_Framework_Address_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet das Normalisieren von Straßennamen
     */
    public function testNormalizeStreet()
    {
        $sAddress = new Marktjagd_Service_Text_Address();
        $address1 = $sAddress->normalizeStreet('AUGUST-BEBEL-STR.');
        $this->assertEquals(
            'August-Bebel-Straße',
            $address1
        );
    }

    /**
     * Test Autobahn-Handling
     */
    public function testExtractionStreet()
    {
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', 'An der A2');
        $streetNumber = $sAddress->extractAddressPart('street_number', 'An der A2');

        $this->assertEquals('An Der A2', $street);
        $this->assertEquals('', $streetNumber);
    }

    /**
     * Test Strasse des 17. Juni 3
     */
    public function testExtractionStreet2()
    {
        $inputString = 'Straße des 17. Juni 3';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('Straße Des 17. Juni', $street);
        $this->assertEquals('3', $streetNumber);
    }

    public function testExtractionStreet3()
    {
        $inputString = 'An der Hafenkante 5';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('An Der Hafenkante', $street);
        $this->assertEquals('5', $streetNumber);
    }

    public function testExtractionStreet4()
    {
        $inputString = 'Schützenplatz 14';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('14', $streetNumber);
    }

    /**
     * Test ob Buchstabe in Hausnummer uncapitalized wird
     */
    public function testExtractionStreet5()
    {
        $inputString = 'Schützenplatz 14A';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('14a', $streetNumber);


        $inputString = 'Schützenplatz 14a';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('14a', $streetNumber);
    }

    /**
     * Straße ohne Hausnummer
     */
    public function testExtractionStreet6()
    {
        $inputString = 'Schützenplatz';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->extractAddressPart('street', $inputString);
        $streetNumber = $sAddress->extractAddressPart('street_number', $inputString);

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('', $streetNumber);
    }

    /**
     * Postleitzahl und Ort
     */
    public function testExtractionCity()
    {
        $inputString = '01067 Dresden';
        $sAddress = new Marktjagd_Service_Text_Address();
        $zipcode = $sAddress->extractAddressPart('zipcode', $inputString);
        $city = $sAddress->extractAddressPart('city', $inputString);

        $this->assertEquals('01067', $zipcode);
        $this->assertEquals('Dresden', $city);


        $inputString = 'D-01067 Dresden';
        $sAddress = new Marktjagd_Service_Text_Address();
        $zipcode = $sAddress->extractAddressPart('zipcode', $inputString);
        $city = $sAddress->extractAddressPart('city', $inputString);

        $this->assertEquals('01067', $zipcode);
        $this->assertEquals('Dresden', $city);
    }

    public function testExtractionMail()
    {
        $sAddress = new Marktjagd_Service_Text_Address();

        $inputString = 'mailto:test@web.de';
        $inputString2 = 'E-Mail: test@web.de';
        $inputString3 = 'test(at)web(dot)de';
        $inputString4 = 'test<at>web<dot>de';
        $expectedString = 'test@web.de';

        $output = $sAddress->normalizeEmail($inputString);
        $output2 = $sAddress->normalizeEmail($inputString2);
        $output3 = $sAddress->normalizeEmail($inputString3);
        $output4 = $sAddress->normalizeEmail($inputString4);

        $this->assertEquals($expectedString, $output);
        $this->assertEquals($expectedString, $output2);
        $this->assertEquals($expectedString, $output3);
        $this->assertEquals($expectedString, $output4);
    }

    public function testExtractionNormalize()
    {
        $inputString = 'Schützenplatz 14A';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $inputString));
        $streetNumber = $sAddress->normalizeStreetNumber($sAddress->extractAddressPart('street_number', $inputString));

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('14a', $streetNumber);

        $inputString = 'Schützenplatz 14A - 18F';
        $sAddress = new Marktjagd_Service_Text_Address();
        $street = $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $inputString));
        $streetNumber = $sAddress->normalizeStreetNumber($sAddress->extractAddressPart('street_number', $inputString));

        $this->assertEquals('Schützenplatz', $street);
        $this->assertEquals('14a - 18f', $streetNumber);
    }

    public function testExtractionStreetAndStreetNumber()
    {
        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreetAndStreetNumber("Schützenplatz 14A - 18F / Ecke Steinstraße");
        $this->assertEquals('Schützenplatz', $eStore->getStreet());
        $this->assertEquals('14a - 18f', $eStore->getStreetNumber());

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreetAndStreetNumber("Schützenplatz 14A/18F / Ecke Steinstraße");
        $this->assertEquals('Schützenplatz', $eStore->getStreet());
        $this->assertEquals('14a / 18f', $eStore->getStreetNumber());

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreetAndStreetNumber("Schützenplatz 14A/18F (Ecke Steinstraße)");
        $this->assertEquals('Schützenplatz', $eStore->getStreet());
        $this->assertEquals('14a / 18f', $eStore->getStreetNumber());
    }

    public function testExtractionPhone()
    {
        $inputString = '+4935141889431';
        $sAddress = new Marktjagd_Service_Text_Address();
        $phoneNumber = $sAddress->normalizePhoneNumber($inputString);
        $this->assertEquals('035141889431', $phoneNumber);

        $inputString = '0049 - 0351/41889431';
        $sAddress = new Marktjagd_Service_Text_Address();
        $phoneNumber = $sAddress->normalizePhoneNumber($inputString);
        $this->assertEquals('035141889431', $phoneNumber);

        $inputString = ' +49 0351-41889431-0 ';
        $sAddress = new Marktjagd_Service_Text_Address();
        $phoneNumber = $sAddress->normalizePhoneNumber($inputString);
        $this->assertEquals('0351418894310', $phoneNumber);
    }
}