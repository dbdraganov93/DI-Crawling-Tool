<?php

class Marktjagd_Collection_Api_Brochure extends Marktjagd_Collection_Api_Abstract
{

    public function __construct()
    {
        $this->_headline = 'brochure_number;type;url;title;tags;start;end;visible_start;'
            . 'store_number;distribution;variety;national;gender;age_range;tracking_bug;options;lang_code;zipcode;layout';
    }

    /**
     * @param Marktjagd_Entity_Api_Brochure $element
     * @param boolean $group
     * @param string $hashType url|pdf
     * @return bool
     */
    public function addElement($element, $group = true, $hashType = 'url')
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        if (!is_null($element->getEnd())
            && strtotime($element->getEnd()) < strtotime('today 00:00:00')) {
            $logger->info('leaflet ' . $element->getUrl() . ' not valid anymore. valid end: ' . $element->getEnd());
            return false;
        }

        if ($configCrawler->crawler->s3->active
            && !preg_match('#^http#', $element->getUrl())
            && $localPath = $element->getUrl()) {
            $s3File = new Marktjagd_Service_Output_S3File('/pdf/', self::getUniqueName($localPath));
            $element->setUrl(preg_replace('#\s+#', '%20', $s3File->saveFileInS3($localPath)));

            unlink($localPath);
        }

        // Wenn Hash noch nicht existiert => neues Element hinzufügen
        $hash = $element->getHash($hashType);

        if (preg_match('#online#i', $element->getTitle())) {
            $element->setVariety('online');
        }

        // skip empty elements
        if ($hash == 'd41d8cd98f00b204e9800998ecf8427e') {
            $logger->log('skip empty element', Zend_Log::DEBUG);
            return false;
        }

        // Prüfen, ob Hash korrekt erzeugt werden konnte
        if (!$hash) {
            if ($hashType == 'pdf') {
                $logger->log('couldn\'t generate hash for pdf ' . $element->getUrl(), Zend_Log::ERR);
            } else {
                $logger->log('unknown hash type ' . $hashType, Zend_Log::ERR);
            }

            return false;
        }

        if (!array_key_exists($hash, $this->_elements)) {
            $this->_elements[$hash] = $element;
            return true;
        } else {
            if ($group) {
                // Wenn gruppiert werden soll => Mergen der SO Nummern und danach aktuelles Element ersetzen
                $aStoreNumbersNew = preg_split('#\s*\,\s*#', $element->getStoreNumber());
                $aStoreNumbersOld = preg_split('#\s*\,\s*#', $this->_elements[$hash]->getStoreNumber());
                $aStoreNumbers = array_merge($aStoreNumbersOld, $aStoreNumbersNew);

                $element->setStoreNumber(implode(',', array_unique($aStoreNumbers)));
                $this->_elements[$hash] = $element;
                return true;
            } else {
                // Wenn Storenumber od. Distribution gesetzt und ungleich, dann neuen Eintrag mit neuem Hash anlegen
                if ((strlen($element->getStoreNumber())
                        && $element->getStoreNumber() != $this->_elements[$hash]->getStoreNumber())
                    || (strlen($element->getDistribution())
                        && $element->getDistribution() != $this->_elements[$hash]->getDistribution())
                ) {
                    $hashNew = md5($hash . uniqid());
                    $this->_elements[$hashNew] = $element;
                    return true;
                } else {
                    // Wenn Storenumber nicht gesetzt oder Storenumber gleich => Duplikatefehler
                    $logger->log('duplicate elements detected, want to add element with hash '
                        . $hash
                        , Zend_Log::DEBUG);
                    return false;
                }
            }

        }
    }

    /**
     * @param string $fileName
     * @return string
     */
    public static function getUniqueName(string $fileName): string
    {
        $unique = preg_replace('#[^\d]#', '-', microtime(true));
        $encodedFilenameWithoutExtension = base64_encode(pathinfo($fileName, PATHINFO_FILENAME));
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        return "$unique-$encodedFilenameWithoutExtension.$extension";
    }
}
