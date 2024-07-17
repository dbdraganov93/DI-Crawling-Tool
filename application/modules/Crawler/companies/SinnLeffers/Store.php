<?php

/* 
 * Store Crawler für Sinn Leffers (ID: 69645)
 */

class Crawler_Company_SinnLeffers_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://sinn.com/';
        $searchUrl = $baseUrl . 'filialen/Alle-Bekleidungshaeuser.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(/filialen/Alle-Bekleidungshaeuser.html\?path=[^"]+)#';
        if (!preg_match_all($pattern, $page, $matchStores)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($matchStores[1] as $storeUrl) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $sPage->open($baseUrl . $storeUrl);

            $page = $sPage->getPage()->getResponseBody();


            if (!preg_match('#<img[^>]*class="img-responsive"[^>]*src="([^"]+)"#', $page, $image)) {
                $this->_logger->info($companyId . ': cant extract images');
                continue;
            }

            if (!preg_match('#<h1>(.+?)</h1>#', $page, $subTitle)) {
                $this->_logger->info($companyId . ': cant get subtitles');
                continue;

            }

            if (!preg_match('#<em>Adresse</em>\s*</h5>\s*<p[^>]*>([^<]*)<br>([^<]*)</p>#', $page, $zipAndCity)) {
                $this->_logger->info($companyId . ': cant collect zipcodes and cities');
                continue;

            }


            if (!preg_match('#<em>Telefon</em>\s*</h5>\s*<p[^>]*>(.+?)</p>#', $page, $phone)) {
                $this->_logger->info($companyId . ': cant collect phone');
                continue;

            }

            if (!preg_match('#<hr>\s*<h5>(.+?)</h5>#', $page, $storeHours)) {
                $this->_logger->info($companyId . ': cant collect storeHours');
                continue;

            }

            if (!preg_match_all('#<a[^>]*accordion-service[^>]*>.+?>([^<]+)</a>#', $page, $service)) {
                continue;

            }

            $id = preg_split('/\s+/', $zipAndCity[2]);

            $eStore->setStoreNumber("ID_" . $id[0])
                ->setTitle("Sinn")
                ->setWebsite($baseUrl . $storeUrl)
                ->setImage($baseUrl . $image[1])
                ->setSubtitle(trim($subTitle[1]))
                ->setStreetAndStreetNumber($zipAndCity[1])
                ->setZipcodeAndCity($zipAndCity[2])
                ->setPhoneNormalized(strip_tags($phone[1]))
                ->setStoreHoursNormalized($storeHours[1])
                ->setFaxNormalized($this->getFaxCollection($page))
                ->setService(trim(implode(', ', $service[1])));

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);

        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName,);
    }

    private function getFaxCollection($page)
    {

        if (preg_match('#<em>Fax</em>\s*</h5>\s*<p[^>]*>([^<]*)</p>#', $page, $faxNumber)) {
            return $faxNumber[1];
        }
        return false;

    }

}