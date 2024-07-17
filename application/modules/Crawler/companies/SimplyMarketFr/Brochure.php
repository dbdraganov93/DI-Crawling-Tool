<?php
/**
 * Brochure Crawler für Simply Market FR (ID: 72330)
 */

class Crawler_Company_SimplyMarketFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores as $eStore) {
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $patternWeeklyBrochure = '#<a[^>]*href="([^"]+?webalogues[^"]+?)"#';
            $patternCustomerMagazine = '#<a[^>]*href="([^"]+?\.pdf)"[^>]*>\s*<img[^>]*alt="([^"]+?)"[^>]*>#i';
            if (!preg_match($patternWeeklyBrochure, $page, $weeklyBrochureInfoUrlMatch)
                && !preg_match_all($patternCustomerMagazine, $page, $customerMagazineInfoMatches)) {
                $this->_logger->err($companyId . ': no brochures for ' . $eStore->getWebsite());
                continue;
            }

            if (count($customerMagazineInfoMatches[1])) {
                for ($i = 0; $i < count($customerMagazineInfoMatches[1]); $i++) {
                    if (preg_match('#BANNIERE\s*FONTIGNAC#', $customerMagazineInfoMatches[2][$i])) {
                        continue;
                    }
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();

                    $eBrochure->setTitle($customerMagazineInfoMatches[2][$i])
                        ->setUrl($customerMagazineInfoMatches[1][$i])
                        ->setStoreNumber($eStore->getStoreNumber())
                        ->setVariety('customer_magazine');

                    $cBrochures->addElement($eBrochure);
                }
            }

            if (strlen($weeklyBrochureInfoUrlMatch[1])) {
                $sPage->open($weeklyBrochureInfoUrlMatch[1]);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<a[^>]*download[^>]*href="([^"]+?_(\d+)\.pdf)"#';
                if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                    $this->_logger->err($companyId . ': unable to get pdf url: ' . $weeklyBrochureInfoUrlMatch[1]);
                    continue;
                }

                $pattern = '#<div[^>]*class="headerLogoTextDate"[^>]*>\s*du\s*(\d+)\s*([^\d\s*]+)?\s*au\s*(\d+)\s*([^\d\s*]+)\s+(\d{4})\s*#i';
                if (!preg_match($pattern, $page, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure validity: ' . $weeklyBrochureInfoUrlMatch[1]);
                    continue;
                }

                $strStart = $validityMatch[1] . '.' . preg_replace('#f.+?er#i', '02', $validityMatch[4]) . '.' . $validityMatch[5];
                $strEnd = $validityMatch[3] . '.' . preg_replace('#f.+?er#i', '02', $validityMatch[4]) . '.' . $validityMatch[5];

                if (strlen($validityMatch[2])) {
                    $strStart = $validityMatch[1] . '.' . preg_replace('#f.+?er#i', '02', $validityMatch[2]) . '.' . $validityMatch[5];
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('Votre rendez-vous-promo')
                    ->setUrl($brochureUrlMatch[1])
                    ->setBrochureNumber($brochureUrlMatch[2])
                    ->setStart($strStart)
                    ->setEnd($strEnd)
                    ->setVariety('leaflet')
                    ->setStoreNumber($eStore->getStoreNumber());

                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);

    }
}
