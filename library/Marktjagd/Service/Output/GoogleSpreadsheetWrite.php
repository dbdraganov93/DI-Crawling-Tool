<?php

require APPLICATION_PATH . '/../vendor/autoload.php';

class Marktjagd_Service_Output_GoogleSpreadsheetWrite
{
    /**
     * @param Marktjagd_Entity_Api_Brochure $eBrochure
     * @throws Exception
     */
    public function addNewGen(Marktjagd_Entity_Api_Brochure $eBrochure): void
    {
        $spreadsheetId = '1EkMdaxOJUce5IR9G2oRzB0l0H45jzFNV1WvFIsVk3UA';
        $sGoogleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $aInfos = $sGoogleSpreadsheet->getFormattedInfos($spreadsheetId);

        if ($this->in_array_recursive($eBrochure->getUrl(), $aInfos)) {
            return;
        }

        $companyId = debug_backtrace()[1]['args'][0];
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aAddInfos = [[
            $companyId,
            $sApi->findCompanyByCompanyId($companyId)['title'],
            $eBrochure->getStart(),
            '',
            $eBrochure->getUrl(),
            $eBrochure->getBrochureNumber(),
        ]];

        $this->writeGoogleSpreadsheet($aAddInfos, $spreadsheetId);
    }

    /**
     * @param $needle
     * @param array $haystack
     * @return bool
     */
    public function in_array_recursive($needle, array $haystack): bool
    {
        foreach ($haystack as $item) {
            if (is_array($item) && $this->in_array_recursive($needle, $item) || $needle == $item) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $aInfos
     * @param string $spreadsheetId
     * @param bool $append
     * @param string $start
     * @param string $sheetName
     * @param bool $newTable
     * @throws Exception
     */
    public function writeGoogleSpreadsheet(array  $aInfos,
                                           string $spreadsheetId = '1pGR4XY03jH1FbPbA-j91nqwW5TUl-DRa6JNEdZ40pKw',
                                           bool   $append = TRUE,
                                           string $start = 'A1',
                                           string $sheetName = 'Sheet1',
                                           bool   $newTable = FALSE,
                                           bool   $newSpreadsheet = FALSE,
                                           string  $inputFormat = 'RAW'): void
    {
        $googleClient = new Marktjagd_Service_Output_GoogleAuth();
        $service = new Google_Service_Sheets($googleClient->getClient());

        if ($newTable) {
            $sheetName = $service->spreadsheets->create(new Google_Service_Sheets_Spreadsheet());
        }

        if ($newSpreadsheet) {
            $exists = FALSE;
            foreach ($service->spreadsheets->get($spreadsheetId)->getSheets() as $singleSheet) {
                if ($singleSheet->getProperties()->getTitle() == $sheetName) {
                    $exists = TRUE;
                    break;
                }
            }
            if (!$exists) {
                $this->createNewSpreadsheet($spreadsheetId, $sheetName);
            }
        }

        $body = new Google_Service_Sheets_ValueRange([
            'range' => $sheetName . '!' . $start,
            'majorDimension' => 'ROWS',
            'values' => $aInfos,
        ]);

        if ($append) {
            $service->spreadsheets_values->append($spreadsheetId, $sheetName . '!' . $start, $body, ['valueInputOption' => $inputFormat]);
        } else {
            $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!' . $start, $body, ['valueInputOption' => $inputFormat]);
        }
    }

    private function createNewSpreadsheet(string $spreadsheetId, string $sheetName)
    {
        $googleClient = new Marktjagd_Service_Output_GoogleAuth();
        $service = new Google_Service_Sheets($googleClient->getClient());

        $request = new Google\Service\Sheets\Request([
            'addSheet' => [
                'properties' => [
                    'title' => $sheetName
                ]
            ]
        ]);

        try {
            $batchUpdateRequest = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [$request]
            ]);

            $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            echo "Sheet '{$sheetName}' created successfully.";
        } catch (Google\Service\Exception $e) {
            echo 'An error occurred: ' . $e->getMessage();
        }

        return $sheetName;
    }

    /**
     * @param int $companyId
     * @param string $spreadsheetId
     * @param string $spreadsheetName
     * @param string $numberFilter
     * @return void
     * @throws Zend_Exception
     */
    public function writeSpecificArticlesToMetaSpreadSheet(int $companyId, string $spreadsheetId, string $spreadsheetName, string $numberFilter = ''): void
    {
        $sGSWrite = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cArticles = $sApi->getActiveArticleCollection($companyId)->getElements();

        $aArticles[] = preg_split('#\s*;\s*#', 'article_number;title;price;text;ean;manufacturer;article_number_manufacturer;'
            . 'suggested_retail_price;trademark;tags;color;size;amount;start;end;visible_start;visible_end;url;'
            . 'shipping;image;store_number;distribution;national;availability;condition;brand;additional_properties');

        foreach ($cArticles as $eArticle) {
            if (!preg_match('#' . $numberFilter . '#', $eArticle->getArticleNumber())) {
                continue;
            }
            $aArticles[] = [
                $eArticle->getArticleNumber() ?: '',
                $eArticle->getTitle() ?: '',
                $eArticle->getPrice() ?: '',
                $eArticle->getText() ?: '',
                $eArticle->getEan() ?: '',
                $eArticle->getManufacturer() ?: '',
                $eArticle->getArticleNumberManufacturer() ?: '',
                $eArticle->getSuggestedRetailPrice() ?: '',
                $eArticle->getTrademark() ?: '',
                $eArticle->getTags() ?: '',
                $eArticle->getColor() ?: '',
                $eArticle->getSize() ?: '',
                $eArticle->getAmount() ?: '',
                $eArticle->getStart() ?: '',
                $eArticle->getEnd() ?: '',
                $eArticle->getVisibleStart() ?: '',
                $eArticle->getVisibleEnd() ?: '',
                $eArticle->getUrl() ?: '',
                $eArticle->getShipping() ?: '',
                $eArticle->getImage() ?: '',
                $eArticle->getStoreNumber() ?: '',
                $eArticle->getDistribution() ?: '',
                $eArticle->getNational() ?: '',
                'in-stock',
                'new',
                '',
                $eArticle->getAdditionalProperties() ?: '',
            ];
        }

        $sGSWrite->writeGoogleSpreadsheet($aArticles, $spreadsheetId, FALSE, 'A1', $spreadsheetName, FALSE, TRUE);

    }
}