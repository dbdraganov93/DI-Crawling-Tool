<?php
/**
 * Brochure Crawler für Auchan FR (ID: 72321)
 */

class Crawler_Company_AuchanFr_Brochure extends Crawler_Generic_Company
{
    private const SEARCH_URL = 'https://catalogue.auchan.fr/api/v1/client/auchan';
    private const MEDIA_API_URL = 'https://media-consult.ubstream.com/api/v1/media/beevirtua/';
    private const DATE_FORMAT = 'd.m.Y';

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open(self::SEARCH_URL);
        $page = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($page->medias as $data) {
            $brochureData = $this->getValidityDates($data);

            $brochureData['number'] = $data->version . '-' . $brochureData['start'] . '-' . $brochureData['end'];

            $brochureMediaBaseUrl = self::MEDIA_API_URL . $data->mediaId;

            $contentJsonUrl = $brochureMediaBaseUrl . '/content/published/version/' . $data->version . '/content.json';
            $sPage->open($contentJsonUrl);
            $contentJson = $sPage->getPage()->getResponseAsJson();

            foreach ($contentJson->documents as $document) {
                $catJsonUrl = $brochureMediaBaseUrl . '/content/published/' . $document->path;
                $sPage->open($catJsonUrl);
                $catJson = $sPage->getPage()->getResponseAsJson();

                $brochureData['url'] = $brochureMediaBaseUrl . '/' . $catJson->pdf->path;

                $eBrochure = $this->generateBrochure($brochureData);

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getValidityDates(Object $data): array
    {
        $startTimestamp = substr($data->validityStartDate, 0, 10);
        $endTimestamp = substr($data->validityEndDate, 0, 10);
        $dates['start'] = date(self::DATE_FORMAT, $startTimestamp);
        $dates['end'] = date(self::DATE_FORMAT, $endTimestamp);

        return $dates;
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Auchan VU')
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($eBrochure->getStart())
            ->setUrl($data['url'])
            ->setBrochureNumber($data['number'])
            ->setVariety('leaflet');

        return $eBrochure;
    }
}
