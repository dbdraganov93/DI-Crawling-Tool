<?php

/*
 * Prospekt Crawler für KIK (ID: 340)
 */

class Crawler_Company_Kik_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $newGenTest = TRUE;
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $searchUrl = 'https://www.kik.de/';

        $activeBrochures = $sApi->findActiveBrochuresByCompany($companyId);
        if ($activeBrochures) {
            $lastModifiedBrochure = array_values($activeBrochures)[0];
        }

        $searchUrlPattern = '#prospekte[^\.]+\.pdf$#i';
        if (!$pdfUrl = $sPage->getUrlsFromUrl($searchUrl, $searchUrlPattern)[0]) {
            throw new Exception('no brochure found with urlPattern:' . $searchUrlPattern);
        }

        $brochureNumber = '';
        if (preg_match('#([^\.]+)\.pdf#', $pdfUrl, $match)) {
            $keyNotes = array_reverse(explode('/', $match[1]));
            $brochureNumber = "$keyNotes[1]_$keyNotes[2]";
        }

        $created = new DateTime($lastModifiedBrochure['created']);
        if (!$lastModifiedBrochure || // don't have an old brochure OR
            ($brochureNumber == $lastModifiedBrochure['brochureNumber'] // the old brochure is the only one available AND older than 14 days
                && $created->diff(new DateTime())->days > 14)) {
            $this->_logger->warn('Current content might be outdated, needs manual check');
        }

        if ($brochureNumber == $lastModifiedBrochure['brochureNumber']) {
            return $this->getSuccessResponse();
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure
            ->setUrl($pdfUrl)
            ->setVariety('leaflet')
            ->setTitle('Wochen Angebote')
            ->setBrochureNumber($brochureNumber);

        $cBrochures->addElement($eBrochure);

        if ($newGenTest) {
            $sGoogleSpreadsheet = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
            $sGoogleSpreadsheet->addNewGen($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
