<?php

/*
 * Prospekt Crawler für Möbel Kraft (ID: 73555, 59, 156)
 */

class Crawler_Company_MobelKraft_Brochure extends Crawler_Generic_Company
{
    private const COMPANIES_DATA = [
        '59' => [
            'name' => 'Hoeffner',
            'brochureTitle' => 'Höffner: Möbelangebote',
        ],
        '156' => [
            'name' => 'Sconto',
            'brochureTitle' => 'SCONTO: Prospekt',
        ],
        '73555' => [
            'name' => 'MobelKraft',
            'brochureTitle' => 'Möbel Kraft',
        ],
    ];

    private array $companyData;
    public function crawl($companyId)
    {
        $this->companyData = self::COMPANIES_DATA[$companyId];
        $sEmail = new Marktjagd_Service_Transfer_Email($this->companyData['name']);
        $dateNormalization = new Marktjagd_Service_DateNormalization_Date();

        $cEmails = $sEmail->generateEmailCollection($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cEmails->getElements() as $eEmail) {
            $pattern = '#item=?\n*=3D([^"<]+)"#';
            if (!preg_match_all($pattern, $eEmail->getText(), $urlMatches)) {
                $this->_logger->info($companyId . ': unable to get brochure url from mail.');
                continue;
            }

            $pattern = '#Laufzeit?:?\s*(\d{2})\.(\d{2})\.?(\d{2,4})?[^\n]+(\d{2})\.(\d{2})\.(\d{2,4})#';
            if (!preg_match_all($pattern, $eEmail->getText(), $validityMatches)) {
                $this->_logger->info($companyId . ': unable to get brochure validity from mail.');
                continue;
            }

            // in case of matching duplicate urls from the email
            $foundIds = array_values(array_unique($urlMatches[1]));
            foreach ($foundIds as $key => $id) {
                $brochureData = [
                    'url' => 'https://prospekte.moebel-kraft.de/prospekte/blaetterkatalog/catalogs/'.$id.'/pdf/complete.pdf',
                    'start' => $dateNormalization->normalize($validityMatches[1][$key].'.'.$validityMatches[2][$key].'.'.$validityMatches[3][$key]),
                    'end' => $dateNormalization->normalize($validityMatches[4][$key].'.'.$validityMatches[5][$key].'.'.$validityMatches[6][$key]),
                    'number' => $id
                ];
                $eBrochure = $this->generateBrochure($brochureData);

                $cBrochures->addElement($eBrochure);
            }

            if (count($cBrochures->getElements())) {
                $sEmail->archiveMail($eEmail);
            }
        }
        return $this->getResponse($cBrochures);
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($this->companyData['brochureTitle'])
            ->setUrl($data['url'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($eBrochure->getStart())
            ->setBrochureNumber($data['number'])
            ->setVariety('leaflet');

        return $eBrochure;
    }
}