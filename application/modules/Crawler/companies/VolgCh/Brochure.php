<?php

/*
 * Brochure Crawler für Volg Ch (ID: 72147)
 */

class Crawler_Company_VolgCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://services.profital.ch/';
        $searchUrl = $baseUrl . 'uploads/list_files.php';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aLanguageInfos = array(
            'de' => array(
                'title' => array(
                    'Aktion' => 'Volg Aktion',
                    'Frische' => 'Volg Frische'
                )
            ),
            'fr' => array(
                'title' => array(
                    'Aktion' => 'Action Volg',
                    'Frische' => 'Action Volg'
                )
            )
        );

        $page = $this->getPageFromProfital($searchUrl);
        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*download="([^"]+?(Frische|Aktion)_(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})_(de|fr|it)\.pdf)"[^>]*>[^<]*<label[^>]*class="timestamp"[^>]*>\s*([^,]+?)\s*,#i';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureMatches[0]); $i++) {
            if (strtotime($brochureMatches[7][$i]) < strtotime('- 1 month')) {
                continue;
            }
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($aLanguageInfos[$brochureMatches[6][$i]]['title'][$brochureMatches[3][$i]])
                ->setUrl($baseUrl . 'uploads/' . $brochureMatches[1][$i])
                ->setVariety('customer_magazine')
                ->setStart($brochureMatches[4][$i])
                ->setEnd($brochureMatches[5][$i])
                ->setVisibleStart($eBrochure->getStart())
                ->setBrochureNumber($brochureMatches[3][$i] . '_' . $brochureMatches[4][$i] . '_' . $brochureMatches[6][$i])
                ->setLanguageCode($brochureMatches[6][$i])
                ->setDistribution($brochureMatches[6][$i]);

            $cBrochures->addElement($eBrochure);

        }

        return $this->getResponse($cBrochures);
    }

    private function getPageFromProfital($searchUrl)
    {
        # have to use curl, because Zend doesn't allow disabling SSL verification on https-URIs
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $searchUrl);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        # disable ssl verification since the CA is not installed as trusted on worker
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($c);
        curl_close($c);
        return $response;
    }

}
