<?php

/*
 * Store Crawler für alldrink (ID: 67885)
 */

class Crawler_Company_Alldrink_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.alldrink.de/';
        $searchUrl = $baseUrl . 'maerkte';
        $storeDetailUrl = $searchUrl . '?markt=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*maerkte\s*=\s*\[\s*(.+?)\];#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#\[\'(\d+?)\'#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeIdMatches)) {
            throw new Exception($companyId . ': unable to get any store ids from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeIdMatches[1] as $singleStoreId) {
            $sPage->open($storeDetailUrl . $singleStoreId);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="suchergebnis"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStoreId);
                continue;
            }

            $pattern = '#>\s*(\d{5}\s*[A-Z]+[^<]+?)(\s*<[^>]*>\s*)+([^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreId);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#(Mo.+?)</td#s';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeHoursMatch)) {
                $this->_logger->info($companyId . ': unable to get store hours: ' . $singleStoreId);
            }

            $aTimes = preg_split('#(\s*<[^>]*>\s*)+#', $storeHoursMatch[1]);

            for ($i = 0; $i < count($aTimes); $i++) {
                if (!strlen($aTimes[$i])) {
                    continue;
                }
                if (preg_match('#((mittag|pause)[^\d]*?)\s*([\d].+)#i', $aTimes[$i], $breakTimeMatch)
                        && preg_match('#([^\d]+?)\s*([\d].+)#', $aTimes[$i - 1], $timeToSplitMatch)) {
                    $strBreakTime = '';
                    $aBreakTime = preg_split('#\s*-\s*#', $breakTimeMatch[3]);
                    $aOldTime = preg_split('#\s*-\s*#', $timeToSplitMatch[2]);
                    if (strlen($strBreakTime)) {
                        $strBreakTime .= ', ';
                    }
                    $strBreakTime = $timeToSplitMatch[1] . ' ' . $aOldTime[0] . '-' . $aBreakTime[0] . ', '
                            . $timeToSplitMatch[1] . ' ' . $aBreakTime[1] . '-' . $aOldTime[1];
                    continue;
                }

                if (strlen($strBreakTime)) {
                    $strBreakTime .= ', ';
                }
                $strBreakTime .= $aTimes[$i];
            }
            
            $pattern = '#>\s*(\d+?\s*\/\s*\d+?)\s*<#';
            if (preg_match($pattern, $storeInfoMatch[1], $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
                if (!preg_match('#^0#', $eStore->getPhone())) {
                    $eStore->setPhone('0' . $eStore->getPhone());
                }
            }
            
            $pattern = '#img[^>]*title=\'([^\']+?)\'[^>]*?src=\'gfx\/leistungen#';
            if (preg_match_all($pattern, $storeInfoMatch[1], $storeServiceMatches)) {
                for ($i = 0; $i < count($storeServiceMatches[1]); $i++) {
                    if (preg_match('#park#i', $storeServiceMatches[1][$i])) {
                        $eStore->setParking($storeServiceMatches[1][$i]);
                        unset($storeServiceMatches[1][$i]);
                        break;
                    }
                }
                $eStore->setService(implode(', ', $storeServiceMatches[1]));
            }
            
            $eStore->setAddress($storeAddressMatch[3], $storeAddressMatch[1])
                ->setStoreHoursNormalized($strBreakTime)
                    ->setStoreNumber($singleStoreId);
            
        $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
