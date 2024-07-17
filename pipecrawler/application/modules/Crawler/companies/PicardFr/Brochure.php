<?php
/**
 * Brochure Crawler für Picard FR (ID: 72378)
 */

class Crawler_Company_PicardFr_Brochure extends Crawler_Generic_Company
{
    private const BASE_URL = 'http://catalogues.picard.fr';

    private Marktjagd_Service_Input_Page $sPage;
    private Marktjagd_Service_Transfer_Http $sHttp;
    private string $localPath;

    public function __construct()
    {
        parent::__construct();
        $this->sPage = new Marktjagd_Service_Input_Page();
        $this->sHttp = new Marktjagd_Service_Transfer_Http();
    }

    public function crawl($companyId)
    {
        $this->localPath = $this->sHttp->generateLocalDownloadFolder($companyId);

        $this->sPage->open(self::BASE_URL);
        $page = $this->sPage->getPage()->getResponseBody();

        $brochurePageUrls = $this->getBrochurePageUrls($page);
        if (empty($brochurePageUrls)) {
            throw new Exception($companyId . ': unable to get brochure page urls.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochurePageUrls as $brochurePageUrl) {
            $pageUrl = self::BASE_URL . $brochurePageUrl;

            $brochureData = $this->getBrochureData($pageUrl, $companyId);
            if (empty($brochureData)) {
                continue;
            }

            $eBrochure = $this->generateBrochure($brochureData);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getBrochurePageUrls(string $page): array
    {
        $brochureUrlsRegex = '#<a[^>]*href="([^"]+)"[^>]*><img[^>]*class="landingCarouselImg">#';
        if (!preg_match_all($brochureUrlsRegex, $page, $brochureUrlMatches)) {
            return [];
        }

        foreach ($brochureUrlMatches[1] as $key => $url) {
            if ($url == '/all-catalogs') {
                unset($brochureUrlMatches[1][$key]);

                $this->sPage->open(self::BASE_URL . $url);
                $page = $this->sPage->getPage()->getResponseBody();

                if (preg_match_all($brochureUrlsRegex, $page, $allCatalogsMatches)) {
                    return array_merge($brochureUrlMatches[1], $allCatalogsMatches[1]);
                }
            }
        }

        return $brochureUrlMatches[1];
    }

    private function getBrochureData(string $url, int $companyId): array
    {
        $this->sPage->open($url);
        $page = $this->sPage->getPage()->getResponseBody();

        $data = $this->getValidity($page);
        if (empty($data)) {
            $this->_logger->err($companyId . ': unable to get brochure validity. ' . $url);
            return [];
        }

        $data['url'] = $this->getBrochureUrl($page, $companyId);
        if (empty($data['url'])) {
            $this->_logger->err($companyId . ': unable to get brochure download url. ' . $url);
            return [];
        }

        $data['number'] = $this->getBrochureNumber($page, $data);

        return $data;
    }

    private function getValidity(string $page): array
    {
        $validityRegex = '#<div[^>]+class="[^"]*headerPeriod[^"]*"[^>]*>[^<]*Du\s*([\d/]+)\s*au\s*([\d/]+)#';
        if (!preg_match($validityRegex, $page, $validityMatch)) {
            return [];
        }

        return [
            'start' => $this->getFormattedValidityDate($validityMatch[1]),
            'end' => $this->getFormattedValidityDate($validityMatch[2]),
        ];
    }

    private function getFormattedValidityDate(string $date): string
    {
        // validity date comes in format DD/MM/YYYY
        $result = explode('/', $date);
        $result = array_reverse($result);
        return implode('-', $result);
    }

    private function getBrochureUrl(string $page, int $companyId): string
    {
        $downloadUrlRegex = '#data-url="([^"]+?)"[^>]*>\s*télécharger#i';
        if (!preg_match($downloadUrlRegex, $page, $brochureDownloadMatch)) {
            $this->_logger->err($companyId . ': unable to get download path. ');
            return '';
        }

        $this->sPage->open(self::BASE_URL . $brochureDownloadMatch[1]);
        $page = $this->sPage->getPage()->getResponseBody();

        $finalDownloadUrlRegex = '#href="([^"]+?)"[^>]*>\s*[^<]*le\s*catalogue#i';
        if (!preg_match($finalDownloadUrlRegex, $page, $brochureDownloadPathMatch)) {
            $this->_logger->err($companyId . ': unable to get final download path.');
            return '';
        }

        $fileContent = $this->getBrochureContent($brochureDownloadPathMatch[1]);
        $brochureName = explode('/', $brochureDownloadMatch[1])[0];
        $filePath = $this->localPath . md5($brochureName) . '.pdf';

        $fh = fopen($filePath, 'w+');
        fwrite($fh, $fileContent);
        fclose($fh);

        return $this->sHttp->generatePublicHttpUrl($filePath);
    }

    private function getBrochureContent(string $filePath): string
    {
        $ch = curl_init(self::BASE_URL . $filePath);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        $fileContent = curl_exec($ch);
        curl_close($ch);

        return $fileContent;
    }

    private function getBrochureNumber(string $page): string
    {
        $title = $this->getBrochureTitle($page);
        if (empty($title)) {
            return $this->getRandomBrochureNumber();
        }

        return md5($title);
    }

    private function getBrochureTitle(string $page): string
    {
        $titleRegex = '#<div[^>]+class="[^"]*headerName[^"]*"[^>]*>([^<]+)#';
        if (!preg_match($titleRegex, $page, $titleMatch)) {
            return '';
        }

        return $titleMatch[1];
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Picard Mag')
            ->setUrl($data['url'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($eBrochure->getStart())
            ->setBrochureNumber($data['number'])
            ->setVariety('leaflet');

        return $eBrochure;
    }
}