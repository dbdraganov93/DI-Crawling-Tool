<?php

/**
 * Abstrakte Company-Crawler Klasse
 *
 * Class Crawler_Generic_Company
 */
abstract class Crawler_Generic_Company extends Crawler_Generic_Abstract
{
    /**
     * Methode, die den Crawlingprozess initiert
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    abstract function crawl($companyId);

    /**
     * Method checks the result of the crawling process and delivers the correct response
     * If $timeToExpirationInDays < 0 no matter what happen, there are regular times with no offers
     *
     * @param $collection
     * @param string $companyId
     * @param string $timeToExpirationInDays
     * @param bool $storeApiCheck
     * @return Crawler_Generic_Response
     * @throws Zend_Exception
     */
    protected function getResponse($collection, $companyId = '', $timeToExpirationInDays = '2', $storeApiCheck = true): Crawler_Generic_Response
    {
        $article = 'Article';
        $brochure = 'Brochure';
        $store = 'Store';
        if (!is_object($collection) || !preg_match("#_($article|$brochure|$store)$#", get_class($collection), $collType)) {
            return $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
        }

        if (!$companyId) {
            $companyId = debug_backtrace()[1]['args'][0];
        }

        switch ($collType[1]) {
            case $article:
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
                break;
            case $brochure:
                if (!count($collection->getElements())) {
                    return $this->setResponseIfNoImport($companyId, $timeToExpirationInDays);
                }
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
                break;
            case $store:
                $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, $storeApiCheck);
                break;
        }


        $fileName = $sCsv->generateCsvByCollection($collection);
        return $this->_response->generateResponseByFileName($fileName, count($collection->getElements()));
    }

    protected function getSuccessResponse(): Crawler_Generic_Response
    {
        $this->_response->setIsImport(false);
        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

        return $this->_response;
    }

    /**
     * returns the response, in case the import has no Elements
     *
     * @param string $companyId
     * @param string $timeToExpirationInDays
     * @return Crawler_Generic_Response
     */
    protected function setResponseIfNoImport($companyId, $timeToExpirationInDays = '2')
    {
        $this->_response->setIsImport(false);

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        if ($timeToExpirationInDays < 0 || $sApi->isActiveBrochureAvailableByCompanyId($companyId, $timeToExpirationInDays)) {
            return $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        }
        return $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
    }

    /**
     * returns a Brochurenumber which are unique and random
     *
     * @param $string
     * @return bool|string
     */
    protected function getRandomBrochureNumber($string = '')
    {
        $t = microtime(true);
        return substr(md5($string . $t), 0, 24);
    }

    /**
     * @param string $msg
     * @param string $msgCat
     */
    protected function metaLog($msg = '', $msgCat = 'info')
    {
        $bt = debug_backtrace();
        $class = $bt[1]['class'];
        $type = $bt[1]['type'];
        $function = $bt[1]['function'];
        $line = array_shift($bt)['line'];
        $this->_logger->$msgCat("$class$type$function() at line $line: $msg");
    }
}

/**
 * Klasse die die Brochüren relevanten Funktionen hält
 *
 * Class Crawler_Brochure_Company
 */
abstract class Crawler_Brochure_Company extends Crawler_Generic_Company
{

}