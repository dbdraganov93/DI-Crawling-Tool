<?php

/**
 * Service zum Generieren der Marktjagd-CSV
 */
class Marktjagd_Service_Output_MarktjagdCsvBrochure extends Marktjagd_Service_Output_MarktjagdCsvAbstract
{

    /**
     * Konstruktor
     *
     * @param int $companyId
     * @param string $modus
     */
    public function __construct($companyId, $modus = 'w')
    {
        $this->_type = 'brochures';
        parent::__construct($companyId, $modus);
    }

    /**
     * @param Marktjagd_Collection_Api_Brochure $collection
     * @return string
     */
    public function generateContent($collection)
    {
        $elements = $collection->getElements();
        $headline = $collection->getHeadline();

        $csvString = $headline . "\n";
        foreach ($elements as $element) {
//            if (preg_match('#amazonaws#', $element->getUrl())) {
//                $pattern = '#(\/[^\/]+?\/)[^\/]+$#';
//                if (!preg_match($pattern, $element->getUrl(),$prefixMatch )) {
//                    throw new Exception('unable to get file prefix: ' . $element->getUrl());
//                }
//                $s3File = new Marktjagd_Service_Output_S3File('/pdf/', date('YmdHim') . '.' . pathinfo($element->getUrl())['extension']);
//                $dateTime = new DateTime();$s3File->moveFileInBucket($prefixMatch[1],
//                    $element->getUrl(),
//                    pathinfo($element->getUrl())['dirname'] . '/'
//                    . $this->_companyId . '/' . $dateTime->format('YmdHisv') . '.pdf');
//
//                $element->setUrl(pathinfo($element->getUrl())['dirname'] . '/'
//                    . $this->_companyId . '/' . $dateTime->format('YmdHisv') . '.pdf');
//            }
            $csvString .= $this->generateContentLine($element);
        }

        return $csvString;
    }

    /**
     * @param Marktjagd_Entity_Api_Brochure $element
     * @return string
     */
    public function generateContentLine($element)
    {
        $csvString = '"' . $element->getBrochureNumber() . '";'
            . '"' . $element->getType() . '";'
            . '"' . $element->getUrl() . '";'
            . '"' . $element->getTitle() . '";'
            . '"' . $element->getTags() . '";'
            . '"' . $element->getStart() . '";'
            . '"' . $element->getEnd() . '";'
            . '"' . $element->getVisibleStart() . '";'
            . '"' . $element->getStoreNumber() . '";'
            . '"' . $element->getDistribution() . '";'
            . '"' . $element->getVariety() . '";'
            . '"' . $element->getNational() . '";'
            . '"' . $element->getGender() . '";'
            . '"' . $element->getAgeRange() . '";'
            . '"' . $element->getTrackingBug() . '";'
            . '"' . $element->getOptions() . '";'
            . '"' . $element->getLanguageCode() . '";'
            . '"' . $element->getZipCode() . '";'
            . $element->getLayout()
            . "\n";
        return $csvString;
    }

    /**
     * Funktion, um aus lokalem Dateipfad öffentlich zugänglichen zu generieren.
     * Bei S3 Speicherung wird die lokal verfügbare Datei vorher zu S3 hochgeladen.
     *
     * @param string $localPath lokaler Dateipfad
     * @return boolean/string
     */
    public function generatePublicBrochurePath($localPath)
    {
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        if (!preg_match('#.*?(/files/(pdf|http|ftp)/.*?)$#', $localPath, $match)) {
            $logger = Zend_Registry::get('logger');
            $logger->err(
                'invalid filename for generating public pdf-file url, filename: '
                . $localPath);

            return false;
        }

        if ($configCrawler->crawler->s3->active) {
            // Save the file to S3 and retrieve the public URL
            $s3File = new Marktjagd_Service_Output_S3File('/pdf/', Marktjagd_Collection_Api_Brochure::getUniqueName($localPath));
            return $s3File->saveFileInS3($localPath);
        } else {
            return $configCrawler->crawler->publicUrl . $match[1];
        }
    }

}
