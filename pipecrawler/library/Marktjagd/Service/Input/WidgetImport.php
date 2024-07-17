<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andreas Pagel
 * Date: 02.01.2019
 * Time: 16:07
 */

class Marktjagd_Service_Input_WidgetImport
{
    /**
     * ISSUU Widget
     *
     * @param string $url
     * @param bool $asPdf
     * @return array
     * @throws Zend_Exception
     */
    public function getBrochureFromIssuu($url, $asPdf = true)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $brochuresImages = [];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Zend_Registry::get('logger')->err("'$url': is not a URL");
            return $brochuresImages;
        }
        $brochureIds = $this->getIds($url, $sPage);
        if (!count($brochureIds)) {
            Zend_Registry::get('logger')->err("The URL: '$url' does not contain any ISSUU Widgets");
            return $brochuresImages;
        }
        foreach ($brochureIds as $brochureId) {
            $brochureParams = $this->getBrochureParams($brochureId, $sPage);
            if (!isset($brochureParams->ownerUsername) || !strlen($brochureParams->ownerUsername) ||
                !isset($brochureParams->documentURI) || !strlen($brochureParams->documentURI)) {
                Zend_Registry::get('logger')->err("The URL for Brochure params: $url does not work");
                continue;
            }
            $brochureJson = $this->getBrochureJson($brochureParams, $sPage);
            if (!is_object($brochureJson)) {
                Zend_Registry::get('logger')->err("The Response from $url did not deliver the expected Json");
                continue;
            }
            $brochureImages = $this->getBrochureImages($brochureJson);
            if (count($brochureImages)) {
                $brochuresImages[$brochureId] = $brochureImages;
            }
        }
        if ($asPdf) {
            return $this->getPdfFromImg($brochuresImages);
        }
        return $brochuresImages;
    }

    /**
     * @param string $url
     * @param Marktjagd_Service_Input_Page as $sPage
     * @return String
     */
    private function getIds($url, $sPage)
    {
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();
        preg_match_all('#(?:issuu\.com|data-configid)[^>]+?\/(\d{8})#i', $page, $ids);
        return $ids[1];
    }

    /**
     * @param string $brochureId
     * @param Marktjagd_Service_Input_Page as $sPage
     * @return object
     */
    private function getBrochureParams($brochureId, $sPage)
    {
        $url = strtr('https://e.issuu.com/config/%%BROCHURE_ID%%.json', [
            '%%BROCHURE_ID%%' => $brochureId,
        ]);
        $sPage->open($url);
        $brochureParams = $sPage->getPage()->getResponseAsJson();
        return $brochureParams;
    }

    /**
     * @param object $brochureParams
     * @param Marktjagd_Service_Input_Page as $sPage
     * @return object
     */
    private function getBrochureJson($brochureParams, $sPage)
    {
        $url = strtr('https://reader3.isu.pub/%%OWNER_USERNAME%%/%%DOCUMENT_URI%%/reader3_4.json', [
            '%%OWNER_USERNAME%%' => $brochureParams->ownerUsername,
            '%%DOCUMENT_URI%%' => $brochureParams->documentURI,
        ]);
        $sPage->open($url);
        $brochureJson = $sPage->getPage()->getResponseAsJson();
        return $brochureJson;
    }

    /**
     * @param object $brochureJson
     * @return array
     * @throws Zend_Exception
     */
    private function getBrochureImages($brochureJson)
    {
        $brochureImages = [];
        if (!isset($brochureJson->document->pages)) {
            Zend_Registry::get('logger')->err("The Structure of the Json has changed");
            return $brochureImages;
        }
        $count = 0;
        foreach ($brochureJson->document->pages as $page) {
            $index = ++$count;
            if (!isset($page->imageUri) || !filter_var($url = "http://$page->imageUri", FILTER_VALIDATE_URL)) {
                Zend_Registry::get('logger')->err("The URL http://$page->imageUri for Page $count is not valid");
                continue;
            }
            if (preg_match('#page_(\d+)#', $page->imageUri, $pageNr)) {
                $index = $pageNr[1];
            }
            $brochureImages[$index] = "http://$page->imageUri";
        }
        return $brochureImages;
    }


    /**
     * @param array $brochuresImages
     * @return array
     * @throws Exception
     */
    public function getPdfFromImg($brochuresImages)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localBrochuresPdf = [];
        foreach ($brochuresImages as $brochureId => $brochureImages) {
            $localPath = $sHttp->generateLocalDownloadFolder($brochureId);
            $localPdfs = [];
            foreach ($this->getLocalFiles($brochureImages, $localPath, $sHttp) as $page => $localBrochureImage) {
                $sPdf->createPdf($localBrochureImage);
                $localPdfs[$page] = $this->getFileWithNewExt($localBrochureImage, 'pdf');
            }
            ksort($localPdfs);
            $localBrochuresPdf[$brochureId] = $sPdf->merge($localPdfs, $localPath);
        }
        return $localBrochuresPdf;
    }

    /**
     * @param array $brochureImages
     * @param string $localPath
     * @param Marktjagd_Service_Transfer_Http as $sHttp
     * @return array
     */
    private function getLocalFiles($brochureImages, $localPath, $sHttp)
    {
        $aPages = [];
        $count = 0;
        foreach ($brochureImages as $brochureImage) {
            $sHttp->getRemoteFile($brochureImage, $localPath);
        }
        foreach (scandir($localPath) as $pagePath) {
            $index = ++$count;
            if (preg_match('#page_(\d+)#', $pagePath, $pageNr)) {
                $index = $pageNr[1];
            }
            $aPages[$index] = $localPath . $pagePath;
        }
        return $aPages;
    }

    /**
     * @param string $file
     * @param string $newExt
     * @return string
     */
    private function getFileWithNewExt($file, $newExt)
    {
        $info = pathinfo($file);
        return ($info['dirname'] ? $info['dirname'] . DIRECTORY_SEPARATOR : '')
            . $info['filename']
            . '.'
            . $newExt;
    }
}