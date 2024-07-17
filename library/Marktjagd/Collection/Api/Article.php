<?php

class Marktjagd_Collection_Api_Article extends Marktjagd_Collection_Api_Abstract
{
    protected $_s3Config;

    public function __construct()
    {
        $this->_headline = 'article_number;title;price;price_is_variable;text;ean;manufacturer;article_number_manufacturer;'
            . 'suggested_retail_price;trademark;tags;color;size;amount;start;end;visible_start;visible_end;url;'
            . 'shipping;image;store_number;distribution;national;lang_code;title_de;title_it;title_fr;text_de;'
            . 'text_it;text_fr;size_de;size_it;size_fr;amount_de;amount_it;amount_fr;color_de;color_it;color_fr;'
            . 'shipping_de;shipping_it;shipping_fr;url_de;url_fr;url_it;additional_properties';

        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $this->_s3Config = $configCrawler->crawler->s3;
    }


    /**
     * @param Marktjagd_Entity_Api_Article $element
     * @param boolean $group
     * @return bool
     */
    public function addElement($element, $group = true, $type = 'complex', $verbose = TRUE)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        $hash = $element->getHash();
        if (preg_match('#simple#', $type)) {
            $hash = md5(
                $element->getTitle()
                . $element->getStart()
                . $element->getEnd()
                . $element->getPrice()
            );
        }

        if ($this->_s3Config->active) {
            $element->setImage(preg_replace('#https://s3\.' . $this->_s3Config->region . '\.amazonaws\.com/#', 's3://', $element->getImage()));
        }

        if (!is_null($element->getSuggestedRetailPrice())
            && (floatval($element->getSuggestedRetailPrice()) == floatval($element->getPrice()))) {
            $element->setSuggestedRetailPrice(NULL);
        }

        // skip empty elements
        if ($hash == 'd41d8cd98f00b204e9800998ecf8427e') {
            $logger->log('skip empty element', Zend_Log::DEBUG);
            return false;
        }

        // Wenn Hash noch nicht existiert => neues Element hinzufügen
        if (!array_key_exists($hash, $this->_elements)) {
            $this->_elements[$hash] = $element;
            if ($verbose) {
                Zend_Debug::dump($element);
            }
            return true;
        } else {
            if ($group) {
                if (preg_match('#complex#', $type)) {
                    // Wenn gruppiert werden soll => Mergen der SO Nummern und danach aktuelles Element ersetzen
                    $aStoreNumbersNew = preg_split('#\s*\,\s*#', $element->getStoreNumber());
                    $aStoreNumbersOld = preg_split('#\s*\,\s*#', $this->_elements[$hash]->getStoreNumber());
                    $aStoreNumbers = array_merge($aStoreNumbersOld, $aStoreNumbersNew);

                    $element->setStoreNumber(implode(',', array_unique($aStoreNumbers)));
                    $this->_elements[$hash] = $element;
                    if ($verbose) {
                        Zend_Debug::dump($element);
                    }
                    return true;
                } elseif (preg_match('#simple#', $type)) {
                    $aStoreNumbersNew = preg_split('#\s*\,\s*#', $element->getStoreNumber());
                    $aStoreNumbersOld = preg_split('#\s*\,\s*#', $this->_elements[$hash]->getStoreNumber());
                    $aStoreNumbers = array_merge($aStoreNumbersOld, $aStoreNumbersNew);

                    $element->setStoreNumber(implode(',', array_unique($aStoreNumbers)));
                    $this->_elements[$hash] = $element;
                }
            } else {
                // Wenn Storenumber gesetzt und ungleich, dann neuen Eintrag mit neuem Hash anlegen
                if (strlen($element->getStoreNumber())
                    && $element->getStoreNumber() != $this->_elements[$hash]->getStoreNumber()
                ) {
                    $hashNew = md5($hash . uniqid());
                    $this->_elements[$hashNew] = $element;
                    if ($verbose) {
                        Zend_Debug::dump($element);
                    }
                    return true;
                } else {
                    // Wenn Storenumber nicht gesetzt oder Storenumber gleich => Duplikatefehler
                    $logger->log('duplicate elements detected, want to add element with hash ' . $hash
                        , Zend_Log::DEBUG);
                    return false;
                }
            }

        }
    }
}