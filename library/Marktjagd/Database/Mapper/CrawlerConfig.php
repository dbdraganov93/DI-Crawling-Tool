<?php

class Marktjagd_Database_Mapper_CrawlerConfig extends Marktjagd_Database_Mapper_Abstract
{
    /**
     * Returns the database table class, if no one exists,
     * default will be created.
     *
     * @return  Marktjagd_Database_DbTable_CrawlerConfig
     */
    public function getDbTable()
    {
        return parent::getDbTable();
    }

    /**
     * Saves data to database. If the primary key is set,
     * data will be updated.
     *
     * @param Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig Object data
     * @param bool $bNull Save also null values
     *
     *
     * @return int|mixed
     */
    public function save(Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig, $bNull = false)
    {
        return parent::_save($oCrawlerConfig, $bNull);
    }

    /**
     * Loads data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     * @param Marktjagd_Database_Entity_CrawlerConfig  $oCrawlerConfig Object for data
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId, Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig)
    {
        return parent::_find($mId, $oCrawlerConfig);
    }

    /**
     * Findet alle Crawler anhand von CompanyId und Typ
     *
     * @param int $companyId
     * @param string $type
     * @param ?string $status
     * @param Marktjagd_Database_Collection_CrawlerConfig $oCrawlerConfig
     * @param string $env
     * @return bool
     */
    public function findByCompanyType($companyId, $type, $status, Marktjagd_Database_Collection_CrawlerConfig $oCrawlerConfig, $env=Marktjagd_Database_Entity_CrawlerConfig::BACKEND_ENV_PROD)
    {
        $result = $this->getDbTable()->findByCompanyType($companyId, $type, $status, $env);

        if (count($result) > 0) {
            $oCrawlerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Findet alle Crawler anhand von CompanyId und Typ
     *
     * @param Marktjagd_Database_Collection_CrawlerConfig $oCrawlerConfig
     * @param string $type
     * @param string $sort
     * @param string $env
     * @return bool
     */
    public function findByType(Marktjagd_Database_Collection_CrawlerConfig $oCrawlerConfig, $type, $sort=null, $env=Marktjagd_Database_Entity_CrawlerConfig::BACKEND_ENV_PROD)
    {
        $result = $this->getDbTable()->findByType($type, $sort, $env);

        if (count($result) > 0) {
            $oCrawlerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Findet einen Crawler anhand der Konfigurations-Id
     * @param $idCrawlerConfig
     * @param Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig
     * @return bool
     */
    public function findById($idCrawlerConfig, Marktjagd_Database_Entity_CrawlerConfig $oCrawlerConfig)
    {
        $result = $this->getDbTable()->findById($idCrawlerConfig);
        if ($result) {
            $oCrawlerConfig->setOptions($result);
            return true;
        }

        return false;
    }

    /**
     * Ermittelt zu allen Crawler-Autoren die Anzahl der aktiven Crawler
     *
     * @return array
     */
    public function countActiveCrawlerByUser()
    {
        $result = $this->getDbTable()->countActiveCrawlerByUser();
        $aResult = array();

        foreach ($result->toArray() as $element) {
            $aResult[$element['name']] = $element['anzahl'];
        }

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl alter und neuer Crawler
     *
     * @return array
     */
    public function countCrawlerByVersionType()
    {
        $result = $this->getDbTable()->countCrawlerByVersionType();
        $aResult = array();

        foreach ($result->toArray() as $element) {
            $aResult[$element['crawlerType']] = $element['count'];
        }

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl von Standort-, Artikel-, PDF-Crawler
     *
     * @return array
     */
    public function countCrawlerByType()
    {
        $result = $this->getDbTable()->countCrawlerByType();
        $aResult = array();

        $aType = array('articles' => 'Artikelcrawler', 'brochures' => 'PDF-Crawler', 'stores' => 'Standortcrawler');

        foreach ($result->toArray() as $element) {
            $aResult[$aType[$element['type']]] = $element['count'];
        }

        return $aResult;
    }

    /**
     * Ermittelt die Anzahl modifizierter Standort-, Artikel-, PDF-Crawler
     *
     * @return array
     */
    public function countModifiedByType()
    {
        $result  = $this->getDbTable()->countModifiedByType();
        $aResult = array();

        foreach ($result->toArray() as $element) {
            $aResult[$element['month']][$element['type']] = $element['count'];
        }

        foreach ($aResult as &$monthElement) {
            if (!array_key_exists('stores', $monthElement)) {
                $monthElement['stores'] = 0;
            }

            if (!array_key_exists('brochures', $monthElement)) {
                $monthElement['brochures'] = 0;
            }

            if (!array_key_exists('articles', $monthElement)) {
                $monthElement['articles'] = 0;
            }
        }

        return $aResult;
    }
}