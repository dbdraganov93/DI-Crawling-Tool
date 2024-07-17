<?php

class Test_Framework_Duplicates_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Artikeln ohne Storenumber und ohne Gruppieren
     */
    public function testArticles1()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
                 ->setStart('01.12.2013')
                 ->setEnd('10.12.2013')
                 ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, false));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');

        $this->assertFalse($cArticle->addElement($eArticle2, false));
        $this->assertTrue(count($cArticle->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Artikeln ohne Storenumber und ohne Gruppieren
     */
    public function testArticles2()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, false));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, false));
        $this->assertTrue(count($cArticle->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Artikeln mit gleicher Storenumber und ohne Gruppieren
     */
    public function testArticles3()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, false));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');

        $this->assertFalse($cArticle->addElement($eArticle2, false));
        $this->assertTrue(count($cArticle->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Artikeln mit gleicher Storenumber und ohne Gruppieren
     */
    public function testArticles4()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, false));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, false));
        $this->assertTrue(count($cArticle->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Artikeln mit gleicher Storenumber und mit Gruppieren
     */
    public function testArticles5()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, true));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, true));
        $this->assertTrue(count($cArticle->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Artikeln mit gleicher Storenumber und mit Gruppieren
     */
    public function testArticles6()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, true));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, true));
        $this->assertTrue(count($cArticle->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Artikeln mit unterschiedlicher Storenumber und mit Gruppieren
     */
    public function testArticles7()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, true));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(2)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, true));
        $this->assertTrue(count($cArticle->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Artikeln mit gleicher Storenumber und mit Gruppieren
     */
    public function testArticles8()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, true));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setArticleNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, true));
        $this->assertTrue(count($cArticle->getElements()) == 1);

        $elements = $cArticle->getElements();
        $element = reset($elements);
        $aStores = explode(',', $element->getStoreNumber());
        $this->assertTrue(count($aStores) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Artikeln ohne Artikelnummer mit gleicher Storenumber und mit Gruppieren
     */
    public function testArticles9()
    {
        $cArticle = new Marktjagd_Collection_Api_Article();

        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');
        $this->assertTrue($cArticle->addElement($eArticle, true));

        $eArticle2 = new Marktjagd_Entity_Api_Article();
        $eArticle2->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setPrice('130.0');

        $this->assertTrue($cArticle->addElement($eArticle2, true));
        $this->assertTrue(count($cArticle->getElements()) == 1);

        $elements = $cArticle->getElements();
        $element = reset($elements);
        $aStores = explode(',', $element->getStoreNumber());
        $this->assertTrue(count($aStores) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Stores mit Storenumbers
     */
    public function testStores1()
    {
        $cStore = new Marktjagd_Collection_Api_Store();

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStoreNumber(1)
               ->setStreet('Teststraße')
               ->setStreetNumber('5b')
               ->setZipcode('01069')
               ->setCity('Teststadt');
        $this->assertTrue($cStore->addElement($eStore));

        $eStore->setStoreNumber(2)
            ->setStreet('Teststraße')
            ->setStreetNumber('5c')
            ->setZipcode('01069')
            ->setCity('Testdorf');
        $this->assertTrue($cStore->addElement($eStore));

        $this->assertTrue(count($cStore->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Stores mit Storenumbers
     */
    public function testStores2()
    {
        $cStore = new Marktjagd_Collection_Api_Store();

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStoreNumber(1)
            ->setStreet('Teststraße')
            ->setStreetNumber('5b')
            ->setZipcode('01069')
            ->setCity('Teststadt');
        $this->assertTrue($cStore->addElement($eStore));

        $eStore->setStoreNumber(1)
            ->setStreet('Teststraße')
            ->setStreetNumber('5b')
            ->setZipcode('01069')
            ->setCity('Teststadt');
        $this->assertFalse($cStore->addElement($eStore));

        $this->assertTrue(count($cStore->getElements()) == 1);
    }

    /**
     *  Testet das Hinzufügen von 2 gleichen Stores ohne Storenumbers
     */
    public function testStores3()
    {
        $cStore = new Marktjagd_Collection_Api_Store();

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreet('Teststraße')
            ->setStreetNumber('5b')
            ->setZipcode('01069')
            ->setCity('Teststadt');
        $this->assertTrue($cStore->addElement($eStore));

        $eStore->setStreet('Teststraße')
            ->setStreetNumber('5b')
            ->setZipcode('01069')
            ->setCity('Teststadt');
        $this->assertFalse($cStore->addElement($eStore));

        $this->assertTrue(count($cStore->getElements()) == 1);
    }

    /**
     *  Testet das Hinzufügen von 2 unterschiedlichen Stores ohne Storenumbers
     */
    public function testStores4()
    {
        $cStore = new Marktjagd_Collection_Api_Store();

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreet('Dorfstraße')
            ->setStreetNumber('50')
            ->setZipcode('01067')
            ->setCity('Testdorf');
        $this->assertTrue($cStore->addElement($eStore));

        $eStore->setStreet('Teststraße')
            ->setStreetNumber('5b')
            ->setZipcode('01069')
            ->setCity('Teststadt');
        $this->assertTrue($cStore->addElement($eStore));

        $this->assertTrue(count($cStore->getElements()) == 2);
    }

    /**
     *  Testet das Hinzufügen von 2 gleichen Stores ohne Storenumbers mit Adressabkürzungen
     */
    public function testStores5()
    {
        $cStore = new Marktjagd_Collection_Api_Store();

        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setStreet('Dorfstraße')
            ->setStreetNumber('50')
            ->setZipcode('01067')
            ->setCity('Testdorf');
        $this->assertTrue($cStore->addElement($eStore));

        $eStore->setStreet('Dorfstr')
            ->setStreetNumber('50')
            ->setZipcode('01067')
            ->setCity('Testdorf');
        $this->assertFalse($cStore->addElement($eStore));

        $eStore->setStreet('Dorfstr.')
            ->setStreetNumber('50')
            ->setZipcode('01067')
            ->setCity('Testdorf');
        $this->assertFalse($cStore->addElement($eStore));

        $this->assertTrue(count($cStore->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Broschüren ohne Storenumber und ohne Gruppieren
     */
    public function testBrochures1()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setTitle('test pdf')
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, false));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(1)
            ->setTitle('test pdf')
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertFalse($cBrochure->addElement($eBrochure2, false));
        $this->assertTrue(count($cBrochure->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Broschüren ohne Storenumber und ohne Gruppieren
     */
    public function testBrochures2()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, false));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(2)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setUrl('http://www.test2.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, false));
        $this->assertTrue(count($cBrochure->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Broschüren mit gleicher Storenumber und ohne Gruppieren
     */
    public function testBrochures3()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, false));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertFalse($cBrochure->addElement($eBrochure2, false));
        $this->assertTrue(count($cBrochure->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Broschüren mit gleicher Storenumber und ohne Gruppieren
     */
    public function testBrochures4()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, false));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(2)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, false));
        $this->assertTrue(count($cBrochure->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Broschüren mit gleicher Storenumber und mit Gruppieren
     */
    public function testBrochures5()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, true));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, true));
        $this->assertTrue(count($cBrochure->getElements()) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Broschüren mit gleicher Storenumber und mit Gruppieren
     */
    public function testBrochures6()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, true));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(2)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, true));
        $this->assertTrue(count($cBrochure->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 unterschiedlichen Broschüren mit unterschiedlicher Storenumber und mit Gruppieren
     */
    public function testBrochures7()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, true));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(2)
            ->setStoreNumber(2)
            ->setStart('01.12.2013')
            ->setEnd('11.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, true));
        $this->assertTrue(count($cBrochure->getElements()) == 2);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Broschüren mit gleicher Storenumber und mit Gruppieren
     */
    public function testBrochures8()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, true));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setBrochureNumber(1)
            ->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, true));
        $this->assertTrue(count($cBrochure->getElements()) == 1);

        $elements = $cBrochure->getElements();
        $element = reset($elements);
        $aStores = explode(',', $element->getStoreNumber());
        $this->assertTrue(count($aStores) == 1);
    }

    /**
     * Testet das Hinzufügen von 2 gleichen Broschüren ohne Broschürennummer mit gleicher Storenumber und mit Gruppieren
     */
    public function testBrochures9()
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');
        $this->assertTrue($cBrochure->addElement($eBrochure, true));

        $eBrochure2 = new Marktjagd_Entity_Api_Brochure();
        $eBrochure2->setStoreNumber(1)
            ->setStart('01.12.2013')
            ->setEnd('10.12.2013')
            ->setUrl('http://www.test.de/');

        $this->assertTrue($cBrochure->addElement($eBrochure2, true));
        $this->assertTrue(count($cBrochure->getElements()) == 1);

        $elements = $cBrochure->getElements();
        $element = reset($elements);
        $aStores = explode(',', $element->getStoreNumber());
        $this->assertTrue(count($aStores) == 1);
    }

    /**
     * Testet, ob das Hinzufügen eines leeren Entities zur Collection nicht durchgeführt wird
     */
    public function testEmptyElement()
    {
        $cStore = new Marktjagd_Collection_Api_Store();
        $eStore = new Marktjagd_Entity_Api_Store();
        $this->assertFalse($cStore->addElement($eStore));

        $cArticle = new Marktjagd_Collection_Api_Article();
        $eArticle = new Marktjagd_Entity_Api_Article();
        $this->assertFalse($cArticle->addElement($eArticle));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochures = new Marktjagd_Entity_Api_Brochure();
        $this->assertFalse($cBrochures->addElement($eBrochures));
    }
}