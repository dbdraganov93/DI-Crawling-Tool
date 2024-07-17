<?php
/**
 * Class Test_Framework_MarktjagdApi_Test
 */
class Test_Framework_MarktjagdApi_Test extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        require_once __DIR__ . '/../scripts/index.php';
    }

    /**
     * Testet Validierung von Bahaviours zu einem Importtyp
     */
    public function testIsValidBehaviour()
    {
        $sMarktjagdApi = new Marktjagd_Service_Input_MarktjagdApi();
        $this->assertTrue($sMarktjagdApi->isValidBehaviour('articles', 'keep'));
        $this->assertTrue($sMarktjagdApi->isValidBehaviour('brochures', 'archive'));
        $this->assertTrue($sMarktjagdApi->isValidBehaviour('stores', 'remove'));
        $this->assertFalse($sMarktjagdApi->isValidBehaviour('store', 'archive'));
    }
}