<?php
/**
 * Brochure Crawler für Casino FR (ID: 72328)
 */

class Crawler_Company_CasinoFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores as $eStore) {
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="promotions"[^>]*>(.+?)<\/ul>#';
            if (!preg_match($pattern, $page, $brochureListMatch)) {
                $this->_logger->info($companyId . ': no brochure for ' . $eStore->getWebsite());
                continue;
            }

            $pattern = '#<li[^>]*>(.+?)<\/li#';
            if (!preg_match_all($pattern, $brochureListMatch[1], $brochureMatches)) {
                $this->_logger->err($companyId . ': unable to get any brochure from list: ' . $eStore->getWebsite());
                continue;
            }

            foreach ($brochureMatches[1] as $singleBrochure) {
                $pattern = '#<p[^>]*>\s*du\s*([^\s]+?)\s*au\s*([^<]+?)\s*<\/p#i';
                if (!preg_match($pattern, $singleBrochure, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure validity: ' . $singleBrochure);
                    continue;
                }

                if (strtotime(preg_replace('#\/#', '.', $validityMatch[2])) < strtotime('now')) {
                    continue;
                }

                $pattern = '#<a[^>]*href\s*=\s*"([^"]+?)"#';
                if (!preg_match($pattern, $singleBrochure, $urlMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure url: ' . $singleBrochure);
                    continue;
                }

                $pattern = '#<h4[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match($pattern, $singleBrochure, $titleMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure title: ' . $singleBrochure);
                    continue;
                }

                $sPage->open($urlMatch[1]);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*data-url="([^"]+?telecharger[^"]+?)"#i';
                if (!preg_match($pattern, $page, $siteUrlMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure site url: ' . $urlMatch[1]);
                    continue;
                }

                $sPage->open('https://catalogue.casino.fr' . $siteUrlMatch[1]);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<a[^>]*href=\'([^\']+?\.pdf)\'#';
                if (!preg_match($pattern, $page, $downloadUrlMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure download url: https://catalogue.casino.fr' . $siteUrlMatch[1]);
                    continue;
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle($titleMatch[1])
                    ->setUrl($downloadUrlMatch[1])
                    ->setStart(preg_replace('#\/#', '.', $validityMatch[1]))
                    ->setEnd(preg_replace('#\/#', '.', $validityMatch[2]))
                    ->setStoreNumber($eStore->getStoreNumber())
                    ->setVariety('leaflet');

                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
