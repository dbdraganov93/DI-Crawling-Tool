<?php
/**
 * Store Crawler für Expert AT (ID: 72783)
 */

class Crawler_Company_ExpertAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sSpread = new Marktjagd_Service_Input_PhpSpreadsheet();

        $sourceUrl = "https://www.expert.at/google-memberlist?MemberShowOn=expert";
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sHttp->getRemoteFile($sourceUrl, $localPath);
        $fileLocation = $localPath . scandir($localPath)[2];

        if (empty($fileLocation)) {
            throw new Exception($companyId . ": Keine Storedatei gefunden");
        }
        $spreadArray = $sSpread->readFile($fileLocation, true, "\t")->getElement(0)->getData();

        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($spreadArray as $spread) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($spread['Geschäftscode'])
                ->setTitle($spread['Name des Unternehmens'])
                ->setStreetAndStreetNumber($spread['Adresszeile 1'])
                ->setZipcode($spread['Postleitzahl'])
                ->setCity($spread['Ort'])
                ->setLongitude($spread['Längengrad'])
                ->setLatitude($spread['Breitengrad'])
                ->setPhone($spread['Primäre Telefonnummer'])
                ->setWebsite($spread['Website'])
                ->setEmail($spread['E-Mail'])
                ->setStoreHoursNormalized($this->getHours($spread));
            $cStore->addElement($eStore);
        }
        return $this->getResponse($cStore, $companyId);

    }
    private function getHours($data) {
        $hourArray = [];
        $wochenTage = ['montags', 'dienstags', 'mittwochs', 'donnerstags','freitags', 'samstags'];


        foreach ($wochenTage as $wTag) {
            array_push($hourArray, substr($wTag, 0, 2) . ": " . $data["Öffnungszeiten ($wTag)"]);
        }
        return implode(',',$hourArray);
    }
}
