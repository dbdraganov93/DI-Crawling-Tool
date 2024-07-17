<?php
/**
 * Brochure Crawler für Magasins U FR (IDs: 72347 - 72350)
 */

class Crawler_Company_MagasinsUFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.magasins-u.com/';
        $searchUrl = $baseUrl . 'catalogue-des-promotions-u';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        $aStoreNumbers = array();
        foreach ($cStores as $eStore) {
            $aStoreNumbers[] = $eStore->getStoreNumber();
        }

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\#pop-choix-magasin"[^>]*id="([^"]+?)"[^>]*data-magasins=("[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $catalogMatches)) {
            throw new Exception($companyId . ': unable to get any catalogues.');
        }

        $aCatalogues = array();
        for ($i = 0; $i < count($catalogMatches[0]); $i++) {
            $pattern = '#(\"|;)(\d+)\/#';
            if (!preg_match_all($pattern, $catalogMatches[2][$i], $storeNumberMatches)) {
                $this->_logger->err($companyId . ': unable to get any store numbers for ' . $catalogMatches[1][$i]);
                continue;
            }
            foreach ($storeNumberMatches[2] as $singleStoreNumber) {
                if (in_array($singleStoreNumber, $aStoreNumbers)) {
                    $aCatalogues[$catalogMatches[1][$i]][] = $singleStoreNumber;
                }
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aCatalogues as $catalogNumber => $aStoresToAssign) {
            $brochureSearchUrl = $baseUrl . 'listeproduits?idope=' . $catalogNumber . '&codemag=' . $aStoresToAssign[0];

            $sPage->open($brochureSearchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<ul[^>]*class="cta[^>]*bt-feuilletez"[^>]*>\s*<li[^>]*>\s*<a[^>]*href="[^"]*catalog\/([^"]+?)"#';
            if (!preg_match($pattern, $page, $brochureNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure number: ' . $catalogNumber);
                continue;
            }

            $sPage->open('http://fluxu-catalogs.elpev.com/catalogs/' . $brochureNumberMatch[1]);
            $jInfos = $sPage->getPage()->getResponseAsJson();

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);

            $brochureSites = array();
            foreach ($jInfos->pages as $pageNo => $pageInfos) {
                $downloadedFile = $sHttp->getRemoteFile($pageInfos->imgURLHD, $localPath);
                rename($downloadedFile, $downloadedFile . '.jpg');
                $brochureSites[$pageNo] = $downloadedFile . '.jpg';
            }

            foreach ($brochureSites as $pageNo => $localPagePath) {
                $sPdf->createPdf($localPagePath);
                $brochureSites[$pageNo] = preg_replace('#\.jpg#', '.pdf', $localPagePath);
            }

            $brochureMerged = $sPdf->merge($brochureSites, $localPath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $strStart = '';
            $strEnd = '';

            $pattern = '#(\d+\s+[^\s]+?)\s+(\d{4})?\s*au[^\d]*(\d+\s+[^\s]+?)\s+(\d{4})#i';
            if (preg_match($pattern, $jInfos->date, $validityMatch)) {
                $strStart = preg_replace('#(\d+)\s+#', '$1. ', $validityMatch[1]);
                if (!strlen($validityMatch[2])) {
                    $strStart .= $validityMatch[4];
                }
                $strEnd = preg_replace('#(\d+)\s+#', '$1. ', $validityMatch[3]) . ' ' . $validityMatch[4];

                $strStart = $sTimes->localizeDate($strStart, 'fr');
                $strEnd = $sTimes->localizeDate($strEnd, 'fr');
            }

            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($brochureMerged))
                ->setTitle($jInfos->name)
                ->setStoreNumber(implode(',', $aStoresToAssign))
                ->setVariety('leaflet')
                ->setBrochureNumber($catalogNumber)
                ->setStart($strStart)
                ->setEnd($strEnd);

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}