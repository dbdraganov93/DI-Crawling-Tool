<?php

/**
 * Class Marktjagd_Entity_Api_Brochure
 */
class Marktjagd_Entity_Api_Brochure extends Marktjagd_Entity_Api_Abstract {

    public const TYPE_DEFAULT = 'default';
    public const TYPE_DISCOVER = 'discover';
    public const TYPE_ADDITIONAL_PAGE = 'additional_page';

    /* @var string */
    protected $brochureNumber;

    /* @var string */
    protected $url;

    /* @var string */
    protected $title;

    /* @var string */
    protected $type = self::TYPE_DEFAULT;

    /* @var string */
    protected $tags;

    /* @var string */
    protected $start;

    /* @var string */
    protected $end;

    /* @var string */
    protected $visibleStart;

    /* @var string */
    protected $visibleEnd;

    /* @var string */
    protected $storeNumber;

    /* @var string */
    protected $distribution;

    /* @var string */
    protected $variety;

    /* @var int */
    protected $national;

    /* @var string */
    protected $gender;

    /* @var string */
    protected $ageRange;

    /* @var string */
    protected $trackingBug;

    /* @var string */
    protected $options;

    /* @var string */
    protected $languageCode;

    /* @var string */
    protected $zipCode;

    /* @var string */
    protected $layout;

    /**
     * @return string
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @param string $layout
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
        $this->setType(self::TYPE_DISCOVER);

        return $this;
    }

    /**
     * @return string
     */
    public function getZipCode()
    {
        return $this->zipCode;
    }

    /**
     * @param string $zipCode
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setZipCode($zipCode)
    {
        $this->zipCode = $zipCode;
        return $this;
    }

    /**
     * @param int $brochureNumber
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setBrochureNumber($brochureNumber) {
        $this->brochureNumber = $brochureNumber;
        return $this;
    }

    /**
     * @return int
     */
    public function getBrochureNumber() {
        return $this->brochureNumber;
    }

    /**
     * @param string $distribution
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setDistribution($distribution) {
        $this->distribution = $distribution;
        return $this;
    }

    /**
     * @return string
     */
    public function getDistribution() {
        return $this->distribution;
    }

    /**
     * @param string $end
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setEnd($end) {
        $this->end = $end;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnd() {
        return $this->end;
    }

    /**
     * @param string $start
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setStart($start) {
        $this->start = $start;
        return $this;
    }

    /**
     * @return string
     */
    public function getStart() {
        return $this->start;
    }

    /**
     * @param string $storeNumber
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setStoreNumber($storeNumber) {
        $this->storeNumber = $storeNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreNumber() {
        return $this->storeNumber;
    }

    /**
     * @param string $tags
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setTags($tags) {
        $this->tags = $tags;
        return $this;
    }

    /**
     * @return string
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * @param string $title
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @param string $url
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * @param string $visibleEnd
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setVisibleEnd($visibleEnd) {
        $this->visibleEnd = $visibleEnd;
        return $this;
    }

    /**
     * @return string
     */
    public function getVisibleEnd() {
        return $this->visibleEnd;
    }

    /**
     * @param string $visibleStart
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setVisibleStart($visibleStart) {
        $this->visibleStart = $visibleStart;
        return $this;
    }

    /**
     * @return string
     */
    public function getVisibleStart() {
        return $this->visibleStart;
    }

    /**
     * @return string
     */
    public function getAgeRange() {
        return $this->ageRange;
    }

    /**
     * @param string $ageRange
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setAgeRange($ageRange) {
        $this->ageRange = $ageRange;
        return $this;
    }

    /**
     * @return string
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     * @param string $gender
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setGender($gender) {
        $this->gender = $gender;
        return $this;
    }

    /**
     * @return int
     */
    public function getNational() {
        return $this->national;
    }

    /**
     * @param int $national
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setNational($national) {
        $this->national = $national;
        return $this;
    }

    /**
     * @return string
     */
    public function getVariety() {
        return $this->variety;
    }

    /**
     * @param string $variety
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setVariety($variety) {
        $this->variety = $variety;
        return $this;
    }

    /**
     * @return string
     */
    public function getTrackingBug() {
        return $this->trackingBug;
    }

    /**
     * @param string $trackingBug
     *
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setTrackingBug($trackingBug) {
        $this->trackingBug = $trackingBug;

        return $this;
    }

    /**
     * @return string
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @param string $options
     *
     * @return Marktjagd_Entity_Api_Brochure
     */
    public function setOptions($options) {
        $this->options = $options;

        return $this;
    }

    /**
     * @return string
     */
    function getLanguageCode() {
        return $this->languageCode;
    }

    /**
     * @param string $languageCode
     *
     * @return Marktjagd_Entity_Api_Brochure
     */
    function setLanguageCode($languageCode) {
        $this->languageCode = $languageCode;
        return $this;
    }

    /**
     * @param string $type url|pdf
     * @return int|string
     */
    public function getHash($type = 'url') {
        if ($type == 'url') {
            if (strlen($this->brochureNumber)) {
                $hash = $this->brochureNumber;
            } else {
                $hash = md5(
                        $this->title
                        . $this->start
                        . $this->end
                        . $this->url
                );
            }
            return $hash;
        }

        if ($type == 'pdf') {
            $sUrl = new Marktjagd_Service_Text_Url();
            // Prüfen ob URL schon auf eine interne Datei verweist
            if ($sUrl->isInternalUrl($this->url)) {
                $localFilePath = $sUrl->convertInternalUrlToAbsolutePath($this->url);
            } else {
                // externe URL => herunterladen via http oder ftp
                $sDownload = new Marktjagd_Service_Transfer_Download();
                $localFilePath = '/tmp/';
                $localFilePath = $sDownload->downloadByUrl($this->url, $localFilePath);
            }

            if ($localFilePath) {
                return md5_file($localFilePath);
            }
        }

        return false;
    }

    public function generatePublicBrochurePath($localPath) {
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        if (!preg_match('#.*?(/files/pdf/.*?)$#', $localPath, $match)) {
            $logger = Zend_Registry::get('logger');
            $logger->err(
                    'invalid filename for generating public pdf-file url, filename: '
                    . $localPath);

            return false;
        }

        return $configCrawler->crawler->publicUrl . $match[1];
    }

}
