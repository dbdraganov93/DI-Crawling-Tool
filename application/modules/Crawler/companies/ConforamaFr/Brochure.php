<?php
/**
 * Brochure Crawler für Conforama FR (ID: 72326)
 */

class Crawler_Company_ConforamaFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.conforama.fr/';
        $searchUrl = $baseUrl . 'service/catalogues';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?catalogues[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $brochureUrlMatches)) {
            throw new Exception($companyId . ': unable to get any brochures: ' . $page);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureUrlMatches[1] as $singleBrochureUrl) {
            $brochureInfoUrl = 'https:' . $singleBrochureUrl . '/js/params.js';

            $sPage->open($brochureInfoUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#var\s*([^\s]+?)\s*=\s*"?([^;"]*?)"?;#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get brochures infos: ' . $brochureInfoUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            $endDate = preg_replace('#(\d{4})(\d{2})(\d{2})#', '$3.$2.$1', $aInfos['dateEnd']);

            if (strtotime($endDate) < strtotime('now')) {
                continue;
            }

            $startDate = preg_replace('#(\d{4})(\d{2})(\d{2})#', '$3.$2.$1', $aInfos['dateStart']);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($aInfos['title'])
                ->setUrl('https:' . $singleBrochureUrl . '/' . $aInfos['globalCode'] . '.pdf')
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
