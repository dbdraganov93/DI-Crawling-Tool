<?php

/*
 * Store Crawler für Finkbeiner (ID: 67891)
 */

class Crawler_Company_Finkbeiner_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        $baseUrl = 'http://www.finkbeiner.biz/';

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        // find menu item 'getraenkemarkt'
        $pattern = '#<a [^>]*?href="\.*\/*([^"]*?)"[^>]*?><img [^>]*?alt="Getränkemarkt[^>]*?>\s*?</a>#i';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('link to menuitem \'getraenkemarkt\' not found in page ' . $baseUrl);
        }

        $menuItemUrl = $baseUrl . $match[1];
        $sPage->open($menuItemUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a [^>]*?href="\.*\/*([^"]*?)"[^>]*?>[^<]*?Adressenverzeichniss[^<]*?</a>#i';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('link to menuitem \'Adressverzeichnis\' not found in page ' . $menuItemUrl);
        }

        $adressListUrl = $baseUrl . $match[1];

        $pattern = '#<a [^>]*?href="\.*\/*([^"]*?)"[^>]*?>[^<]*?Öffnungszeiten[^<]*?</a>#i';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('link to menuitem \'Öffnungszeiten\' not found in page ' . $menuItemUrl);
        }

        $sPage->open($adressListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#window.open\(\'\.*\/*([^\']+?)\'#';
        if (!preg_match_all($pattern, $page, $matches)) {
            throw new Exception('can\'t find any store in ' . $adressListUrl);
        }

        foreach ($matches[1] as $storeUrl) {
            $pattern ='#index=(\d+?)$#';

            if (!preg_match($pattern, $storeUrl, $match)) {
                $this->_logger->err('can\'t find store number in url ' . $storeUrl);
                continue;
            }

            $storeNumber = $match[1];
            $storeUrl = $baseUrl . $storeUrl;
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($storeNumber);
            $eStore->setWebsite($storeUrl);

            // title + pictures table
            $pattern = '#<td [^>]*?>[^<]*?<strong>(.*?)</strong>'.
                '.*?<table [^>]*?>(.*?)</table>#';
            if (preg_match($pattern, $page, $match)) {
                $title = preg_replace('#,.*?$#', '', $match[1]);
                $title = strip_tags($title);
                $eStore->setTitle($title);

                // pictures
                if(preg_match_all('#<img [^>]*?src="\.*\/*([^"]*?)"#', $match[2], $matches)){
                    $pictures = ($matches[1]);
                    foreach ($pictures as $k => $picUrl) {
                        $pictures[$k] = $baseUrl . $picUrl;
                    }
                    $eStore->setImage(implode(',', $pictures));
                }
            }

            // adress and telno blocks
            $pattern = '#<td [^>]*?>Adresse</td>(.*?)<a [^>]*?href="([^"]*?)"#i';
            if (!preg_match($pattern, $page, $match)) {
                $this->_logger->err('cant get address for Store ');
                continue;
            }

            //telno
            $pattern = '#<br>Telefon:([^<]*?)<br#';
            if (preg_match($pattern,$match[1],$subMatch)) {
                $eStore->setPhoneNormalized($subMatch[1]);
            }

            $pattern = '#\?street\d=(.*?)&zip\d=(.*?)&city\d=(.*?)&.*?country\d=(.*?)&#';
            if (preg_match($pattern,$match[2],$subMatch)) {
                if (!preg_match('#de#i',$subMatch[4])) {
                    continue;
                }
                $eStore->setStreetAndStreetNumber($subMatch[1]);
                $eStore->setZipcode($subMatch[2]);
                $eStore->setCity($subMatch[3]);

            } else {
                $this->_logger->err('cant address data for Store ' . $storeUrl);
                continue;
            }

            //hours
            $pattern = '#<td [^>]*?>[^<]*ffnungszeiten</td>\s*?</tr>\s*?<tr>\s*?'.
                '<td [^<]*?<br>(.*?)</td>#i';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            //add services
            $pattern = '#<img [^>]*?>\s*?</td>\s*?<td>(.*?)</td>#';
            if (preg_match_all($pattern,$page,$matches)) {
                $service = '';
                foreach ($matches[1] as $serviceStr) {
                    $service .= '- ' . trim(preg_replace('#<[^>]*>#', '', $serviceStr))  . '<br>';
                }

                $eStore->setService($service);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
