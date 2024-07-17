<?php

/* 
 * Prospekt Crawler für Sonderpreis Baumarkt (ID: 28831)
 */

class Crawler_Company_SonderpreisBaumarkt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sonderpreis-baumarkt.de/';
        $searchUrl = $baseUrl . 'angebote/aktuelle-werbung';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'next';
        $weekNo = date('W', strtotime($week . ' week'));
        $currentYear = (new DateTime)->format("Y");

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="emotion--wrapper"[^>]*data-controllerUrl="\/([^"]+?)"[^>]*>\s*#';
        if (!preg_match($pattern, $page, $brochureUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure url.' . $searchUrl);
        }

        // Opens embedded yumpu URL iframe
        $sPage->open($baseUrl . $brochureUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#https:\/\/www.yumpu.com\/de\/embed\/view\/([^"]*)#';
        if (!preg_match_all($pattern, $page, $assignmentFileMatch)) {
            throw new Exception(
                $companyId . ': unable to get yumpu.com URL from brochure' . $baseUrl . $brochureUrlMatch[1]
            );
        }

        // Get yumpu URL id -> get the second one that should be the 'next' week
        $sPage->open($assignmentFileMatch[0][1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#https:\/\/www.yumpu.com\/de\/document\/view\/\d{8}\/' . $currentYear . '-kw' . $weekNo . '#';
        if (!preg_match($pattern, $page, $brochureDownloadUrl)) {
            throw new Exception(
                $companyId . ': unable to get yumpu.com Download brochure id page ' . $assignmentFileMatch[0]
            );
        }

        // Get yumpu download page
        $sPage->open($brochureDownloadUrl[0]);
        $page = $sPage->getPage()->getResponseBody();

        // Filter brochure URL from download page
        $pattern = '#(?<=href=")https:\/\/www.yumpu.com\/de\/document\/download\/([^"]*)#';
        if (!preg_match($pattern, $page, $brochureDownloadUrl)) {
            throw new Exception($companyId . ': unable to find download PDF in URL: ' . $brochureDownloadUrl[0]);
        }

        $this->_logger->info($companyId . ': Starting PDF download :' . $brochureDownloadUrl[0]);
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $brochure = $this->getRemoteFileWithCookies($brochureDownloadUrl[0], $localPath, 'brochure.pdf');
        $this->_logger->info($companyId . ': PDF download Done!');

        $aPdfData = $sPdf->extractText($brochure);
        $pattern = '#gültig\s*vom\s*(?<from>\d{2}\.\d{2}\.\d{4})\s*bis\s*(?<until>\d{2}\.\d{2}\.\d{4})#';
        if (!preg_match($pattern, $aPdfData, $validityMatch)) {
            throw new Exception($companyId . ': unable to find validity date in PDF for week ' . $weekNo);
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Wochen Angebote KW' . $weekNo)
            ->setVariety('leaflet')
            ->setUrl($brochure)
            ->setBrochureNumber($currentYear . $weekNo)
            ->setStart($validityMatch['from'])
            ->setEnd($validityMatch['until'])
            ->setVisibleStart($eBrochure->getStart());

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * A copy from $sHttp->_curlOneFile() but with a cookie file to bypass yumpu.com
     */
    public function getRemoteFileWithCookies($url, $localPath, $dataName)
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        if (isset($parsedUrl['path'])) {
            $path = $parsedUrl['path'];
        } else {
            $path = '/';
        }

        if (strlen($dataName)) {
            $fileName = $dataName;
        } else {
            $aUrl = explode('/', $path);
            $fileName = preg_replace('#[\.]([^\.]+?\.[^\.]+)#', '$1', end($aUrl));
        }
        if (!strlen($fileName)) {
            $fileName = 'tmp_file';
        }

        if (isset($parsedUrl['query'])) {
            $path .= '?' . $parsedUrl['query'];
        }

        if (isset($parsedUrl['port'])) {
            $port = $parsedUrl['port'];
        } else {
            $port = '80';
        }

        $newUrl = $parsedUrl['scheme'] . '://' . $host . $path;

        // Difference from $sHttp->_curlOneFile() is that here we need to create cookies
        $cookie_path = 'cookie.txt';
        if (defined('COOKIE_PATH_FOR_CURL') && !empty(COOKIE_PATH_FOR_CURL)) {
            $cookie_path = COOKIE_PATH_FOR_CURL;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $newUrl);
        curl_setopt($ch, CURLOPT_PORT, $port);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            "facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)"
        );
        // Add cookie path to curl
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_path);

        if ($parsedUrl['scheme'] == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            if ($port == '80') {
                curl_setopt($ch, CURLOPT_PORT, 443);
            }
        }

        if (array_key_exists('user', $parsedUrl)
            && strlen($parsedUrl['user'])
            && array_key_exists('pass', $parsedUrl)
            && strlen($parsedUrl['pass'])
        ) {
            curl_setopt($ch, CURLOPT_USERPWD, $parsedUrl['user'] . ':' . $parsedUrl['pass']);
        }

        $result = curl_exec($ch);

        if ($result === false
            || curl_errno($ch) != 0
        ) {
            curl_close($ch);
            return false;
        }

        file_put_contents($localPath . $fileName, $result);
        curl_close($ch);

        return $localPath . $fileName;
    }
}
