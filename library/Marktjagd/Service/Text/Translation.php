<?php

/* 
 * Bietet Lokalisierungs-Funktionen
 */

class Marktjagd_Service_Text_Translation {
    
    protected $_aLanguageForZipcode;
    
    public function __construct() {
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $this->_aLanguageForZipcode = $sExcel->readFile(APPLICATION_PATH . '/../public/files/dataCh/CH_PLZ_Sprachen_Zuordnung.csv', TRUE, ';')->getElement(0)->getData();
    }
    
    /**
     * Ermittelt anhand der übergegeben PLZ die Hauptsprache in dem Gebiet
     * 
     * @param string $zipcode
     * @return string language code
     */
    public function findLanguageCodeForZipcode($zipcode) {
        foreach ($this->_aLanguageForZipcode as $singleLanguageZipcode) {
            if (preg_match('#^' . $singleLanguageZipcode['PLZ'] . '$#', trim($zipcode))) {
                return strtolower($singleLanguageZipcode['Sprache']);
            }
        }
        
        return NULL;
    }

    public function findZipcodesForLanguageCode($languageCode)
    {
        $aZipcode = array();

        foreach ($this->_aLanguageForZipcode as $singleLanguageZipcode) {
            if (preg_match('#^' . $languageCode . '$#i', $singleLanguageZipcode['Sprache'])) {
                $aZipcode[] = trim($singleLanguageZipcode['PLZ']);
            }
        }

        return $aZipcode;
    }

    public function findAllZipcodes()
    {
        $aZipcode = array();
        foreach ($this->_aLanguageForZipcode as $singleLanguageZipcode) {
            $aZipcode[] = trim($singleLanguageZipcode['PLZ']);
        }

        return $aZipcode;
    }

}