<?php

/**
 * * Storecrawler für Globus ID: 422
 */

class Crawler_Company_Globus_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#Standortliste#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $addressData = $singleRow['Adressdaten'];
            if (!is_string($singleRow['Adressdaten'])) {
                $addressData = $singleRow['Adressdaten']->getRichTextElements()[count($singleRow['Adressdaten']->getRichTextElements()) - 1]->getText();
            }
            $aInfos = preg_split('#\n#', $addressData);
            for ($i = 0; $i < count($aInfos); $i++) {
                if (preg_match('#\d{5}\s+[A-ZÄÖÜ]#', $aInfos[$i])) {
                    $aAddress = preg_split('#\s*,\s*#', $aInfos[$i]);
                    if (count($aAddress) < 2) {
                        $aAddress = [
                            0 => $aInfos[$i - 1],
                            1 => $aInfos[$i]
                        ];
                    }
                    break;
                }
            }


            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber(strtolower($singleRow['Marktkürzel']));

            for ($j = 0; $j < count($aAddress); $j++) {
                if (preg_match('#\d{5}\s+[A-ZÄÖÜ]#', $aAddress[$j])) {
                    $eStore->setAddress($aAddress[$j - 1], $aAddress[$j]);
                    $eStore->setStreetNumber(trim(preg_replace('#(\d+) - - - (\d+)#', '$1-$2', $eStore->getStreetNumber()), ','));
                }
            }

            foreach ($aInfos as $singleAttribute) {
                if (preg_match('#^([^@]+?)\.de$#', $singleAttribute)) {
                    if (!preg_match('#^www\.#', $singleAttribute)) {
                        $singleAttribute = 'www.' . $singleAttribute;
                    }
                    $eStore->setWebsite('https://' . $singleAttribute);
                } elseif (preg_match('#@#', $singleAttribute)) {
                    $eStore->setEmail(preg_replace('#e-?mail:\s*#i', '', $singleAttribute));
                } elseif (preg_match('#fon#', $singleAttribute)) {
                    $eStore->setPhoneNormalized($singleAttribute);
                } elseif (preg_match('#fax#', $singleAttribute)) {
                    $eStore->setFaxNormalized($singleAttribute);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
