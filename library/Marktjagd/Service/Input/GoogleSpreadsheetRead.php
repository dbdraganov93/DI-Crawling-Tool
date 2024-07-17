<?php

require APPLICATION_PATH . '/../vendor/autoload.php';

class Marktjagd_Service_Input_GoogleSpreadsheetRead
{
    /**
     * @param string $spreadsheetId
     * @param string $start
     * @param string $end
     * @param string $sheetName
     * @return array
     * @throws Exception
     */
    public function getFormattedInfos(string $spreadsheetId, string $start = 'A1', string $end = 'F', string $sheetName = 'Sheet1'): array
    {
        $oSpreadSheetInfo = $this->readGoogleSpreadsheets($spreadsheetId, $start, $end, $sheetName);

        $aHeader = [];
        $aData = [];
        foreach ($oSpreadSheetInfo->getValues() as $singleRow) {
            if (!count($aHeader)) {
                $aHeader = $singleRow;
                foreach ($aHeader as &$singleValue) {
                    $singleValue = trim($singleValue);
                }
                continue;
            }
            foreach ($aHeader as $key => $value) {
                if (!array_key_exists($key, $singleRow)) {
                    $singleRow[$key] = '';
                }
            }
            foreach ($singleRow as &$singleValue) {
                $singleValue = trim($singleValue);
            }
            $aData[] = array_combine($aHeader, $singleRow);
        }
        return $aData;
    }

    /**
     * @param string $spreadsheetId
     * @param string $start
     * @param string $end
     * @param string $sheetName
     * @return Google_Service_Sheets_ValueRange
     * @throws Exception
     */
    public function readGoogleSpreadsheets(string $spreadsheetId, string $start, string $end, string $sheetName): Google_Service_Sheets_ValueRange
    {
        $googleClient = new Marktjagd_Service_Output_GoogleAuth();
        $service = new Google_Service_Sheets($googleClient->getClient());

        return $service->spreadsheets_values->get($spreadsheetId, $sheetName . '!' . $start . ':' . $end);
    }

    /**
     * @param $companyId
     * @return array|bool
     * @throws Exception
     */
    public function checkForSurveyFromSpreadsheet($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $aSpreadsheetInfos = $this->getFormattedInfos('1OHfcQ3s-GW9xPDGEJSchtXlnBZh4XGdQWQNY5i-T6ag', 'A1', 'E');

        $surveyInfos = [];
        foreach ($aSpreadsheetInfos as $row => $singleColumn) {
            if ($singleColumn['companyId'] != $companyId
                || strlen($singleColumn['closed'])
                || !strlen($singleColumn['created by'])
                || !strlen($singleColumn['url to survey'])
                || !$sPage->checkUrlReachability($singleColumn['url to survey'])) {
                continue;
            }
            $surveyInfos = [
                'row' => $row,
                'url to survey' => $singleColumn['url to survey'],
                'insert after page' => $singleColumn['insert after page']
            ];
        }
        if (!count($surveyInfos)) {
            return FALSE;
        }
        return $surveyInfos;
    }

    /**
     * @param string $customerName name of the customer
     * @param string $start coordinate of the start cell
     * @param string $end coordinate of the end cell
     * @return array|null
     * @throws Exception
     */
    public function getCustomerData(string $customerName, string $start = 'A1', string $end = 'Z', bool $all = FALSE): ?array
    {
        $aSpreadsheetInfos = $this->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', $start, $end, $customerName);
        if (count($aSpreadsheetInfos)) {
            return $all ? $aSpreadsheetInfos : reset($aSpreadsheetInfos);
        }

        return NULL;

    }

    /**
     * @param string $customerName name of the customer
     * @param string $start coordinate of the start cell
     * @param string $end coordinate of the end cell
     * @return array|null
     * @throws Exception
     */
    public function getPinterestData(string $customerName, string $start = 'A1', string $end = 'Z'): ?array
    {
        $aSpreadsheetInfos = $this->getFormattedInfos('1EcmMjn4da51a-ciqe1xg2vHfpuddldAh3wEFfqzMbN0', $start, $end, $customerName);
        if (count($aSpreadsheetInfos)) {
            return $aSpreadsheetInfos;
        }

        return NULL;

    }

    public function getBgArticleCrawlerUTMs(): string
    {
        $customerData = $this->getCustomerData('articleCrawlersBg');

        if (empty($customerData['UTM params'])) {
            throw new Exception('UTM params for articleCrawlersBg not found');
        }

        return $customerData['UTM params'];
    }
}
