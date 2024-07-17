<?php

class Wgw_Service_Import_StoreConvert
{

    protected $_idCompany;
    protected $_aRegions;

    public function __construct($idCompany)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect('dataAt', TRUE);
        $localJRegionFile = $sFtp->downloadFtpToDir('regions.json', $localPath);

        $sFtp->close();

        $this->_aRegions = json_decode(file_get_contents($localJRegionFile), TRUE);
        $this->_idCompany = $idCompany;
    }

    public function convertStore($eStore)
    {
        if (!$this->_aRegions[$eStore->getZipcode()]) {
            return FALSE;
        }

        $aTimes = [];
        if (strlen($eStore->getStoreHours())) {
            $aTimes = $this->_convertTimes($eStore->getStoreHours());
        }
        $aInfos['data'] = [
            'type' => 'store',
            'attributes' => [
                'name' => $eStore->getTitle(),
                'address' => $eStore->getStreet() . ' ' . $eStore->getStreetNumber(),
                'generalInfo' => $eStore->getText(),
                'latitude' => $eStore->getLatitude(),
                'longitude' => $eStore->getLongitude(),
                'postalCode' => $eStore->getZipcode(),
                'active' => true,
//                'links' => [
//                    [
//                        'name' => 'Store Website',
//                        'url' => $eStore->getWebsite(),
//                    ],
//                ],
                'workingHours' => $aTimes
            ],
            'relationships' => [
                'company' => [
                    'data' => [
                        'type' => 'company',
                        'id' => $this->_idCompany
                    ]
                ],
                "district" => [
                    "data" => [
                        "type" => "region",
                        "id" => $this->_aRegions[$eStore->getZipcode()]
                    ]
                ]
            ]
        ];

        if (strlen(trim($eStore->getEmail()))) {
            $aInfos['data']['attributes']['contacts'] = [
                [
                    'variant' => 1,
                    'contact' => $eStore->getEmail(),
                    'name' => 'Store Contact'
                ]
            ];
        }

        $jStoreInfos = json_encode($aInfos);

        return $jStoreInfos;
    }

    protected function _convertTimes($strTimes)
    {
        $aWeekDaysLoc =
            [
                'Mo' => 'monday',
                'Di' => 'tuesday',
                'Mi' => 'wednesday',
                'Do' => 'thursday',
                'Fr' => 'friday',
                'Sa' => 'saturday',
                'So' => 'sunday'
            ];

        $aWgwWeek = [];

        foreach ($aWeekDaysLoc as $germanDay => $englishDay) {
            if (!preg_match('#' . $germanDay . '#i', $strTimes)) {
                $aWgwWeek[$englishDay] = ['closed' => TRUE];
                continue;
            }

            $aWgwWeek[$englishDay] = ['closed' => FALSE];
        }

        $aTimes = preg_split('#\s*,\s*#', $strTimes);

        foreach ($aTimes as $singleDay) {
            $strDay = preg_replace('#([A-Z][a-z])\s+(.+)#', '$1', $singleDay);
            $strTime = preg_replace('#([A-Z][a-z]\s+)(.+)#', '$2', $singleDay);

            $aTime = preg_split('#\s*-\s*#', $strTime);
            if (!array_key_exists('firstShift', $aWgwWeek[$aWeekDaysLoc[$strDay]])) {
                $aWgwWeek[$aWeekDaysLoc[$strDay]]['firstShift'] = [
                    'start' => $aTime[0],
                    'end' => $aTime[1]
                ];
            } else {
                $aWgwWeek[$aWeekDaysLoc[$strDay]]['secondShift'] = [
                    'start' => $aTime[0],
                    'end' => $aTime[1]
                ];
            }
        }

        return $aWgwWeek;
    }
}