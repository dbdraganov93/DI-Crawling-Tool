<?php
/**
 * Prospekt Crawler für Leader Price FR (ID: 72336)
 */

class Crawler_Company_LeaderPriceFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.leaderprice.fr/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $aBrochures = array();
        foreach ($cStores as $eStore) {
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*consulter#i';
            if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
                $this->_logger->info($companyId . ': no brochure for ' . $eStore->getWebsite());
                continue;
            }

            $sPage->open($baseUrl . $brochureInfoUrlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<section[^>]*id="catalogue"[^>]*>\s*<iframe[^>]*src="\/([^"]+?\/([^\/]+?)\/)"#';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure url: ' . $eStore->getWebsite());
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Mon catalogue de la semaine')
                ->setUrl($baseUrl . $brochureUrlMatch[1] . 'files/assets/common/downloads/publication.pdf')
                ->setStoreNumber($eStore->getStoreNumber())
                ->setVariety('leaflet')
                ->setBrochureNumber($brochureUrlMatch[2])
                ->setTags('détergent, fromage, nettoyeur, les vêtements féminins, savon, nourriture pour chats, chaussettes, vin, saumon, pommes');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}