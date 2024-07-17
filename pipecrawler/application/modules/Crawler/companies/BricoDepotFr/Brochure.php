<?php
/**
 * Brochure Crawler für Brico Depot FR (ID: 72325)
 */

class Crawler_Company_BricoDepotFr_Brochure extends Crawler_Generic_Company
{
    private const SEARCH_URL = 'https://www.bonialserviceswidget.de/fr/stores/411262350/brochures?storeId=411262350&publisherId=2053&limit=100&hasOffers=true&lng=2.345044&lat=48.947388';
    private const DOWNLOAD_BASE_URL = 'https://aws-ops-bonial-biz-production-published-content-pdf.s3-eu-west-1.amazonaws.com/';
    private const DATE_FORMAT = 'd.m.Y';

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open(self::SEARCH_URL);

        $list = $sPage->getPage()->getResponseAsJson();

        if (empty($list) || !isset($list->brochures) || empty($list->brochures)) {
            throw new Exception($companyId . ': unable to get brochure list.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($list->brochures as $brochure) {
            $brochureData = $this->getBrochureData($brochure);

            $eBrochure = $this->generateBrochure($brochureData);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getBrochureData(object $brochure): array
    {
        return [
            'title' => $brochure->title,
            'url' => self::DOWNLOAD_BASE_URL . $brochure->contentId . '/' . $brochure->contentId . '.pdf',
            'start' => date(self::DATE_FORMAT, strtotime($brochure->validFrom)),
            'end' => date(self::DATE_FORMAT, strtotime($brochure->validUntil)),
            'number' => $brochure->id
        ];
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($data['title'])
            ->setUrl($data['url'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($eBrochure->getStart())
            ->setBrochureNumber($data['number'])
            ->setVariety('leaflet')
            ->setTags('compresseur, accumulateur, perceuse, douche, aspirateur');

        return $eBrochure;
    }
}
