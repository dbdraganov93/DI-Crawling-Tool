#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$sDbCompany = new Marktjagd_Database_Service_Company();
$sDbCompanyConfig = new Marktjagd_Database_Service_QualityCheckCompanyInfos();

$aCompany = $sDbCompany->findAll();
$aCompanyConfigs = $sDbCompanyConfig->findAll();
$aCompanyInfos = array();

/** @var $singleCompany Marktjagd_Database_Entity_Company */
foreach ($aCompany as $singleCompany)
{
    /** @var $singleConfig Marktjagd_Database_Entity_QualityCheckCompanyInfos*/
    foreach ($aCompanyConfigs as $singleConfig)
    {
        if ($singleCompany->getIdCompany() == $singleConfig->getIdCompany())
        {
            $aCompanyInfos[$singleCompany->getIdCompany()]['stores_check'] = $singleConfig->getStores();
            $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_check'] = $singleConfig->getBrochures();
            $aCompanyInfos[$singleCompany->getIdCompany()]['products_check'] = $singleConfig->getProducts();
            break;
        }
    }

    $aCompanyInfos[$singleCompany->getIdCompany()]['stores_count'] = 0;
    $aCompanyInfos[$singleCompany->getIdCompany()]['stores_last_modified'] = false;
    $aCompanyInfos[$singleCompany->getIdCompany()]['stores_last_import'] = false;

    if ($aCompanyInfos[$singleCompany->getIdCompany()]['stores_check'])
    {
        $aStores = $sApi->findAllStoresForCompany($singleCompany->getIdCompany());

        if (count($aStores))
        {
            $firstStore = reset($aStores);
            $aCompanyInfos[$singleCompany->getIdCompany()]['stores_last_import'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'store'));
            $aCompanyInfos[$singleCompany->getIdCompany()]['stores_last_modified'] = strtotime($firstStore['datetime_modified']);
            if (strtotime($firstStore['datetime_modified']) < strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'store')))
            {
                $aCompanyInfos[$singleCompany->getIdCompany()]['stores_last_modified'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'store'));
            }
            $aCompanyInfos[$singleCompany->getIdCompany()]['stores_count'] = count($aStores);
        }
    }

    if ($aCompanyInfos[$singleCompany->getIdCompany()]['brochures_check'])
    {
        $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_count'] = 0;
        $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_modified'] = false;
        $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_import'] = false;

        $aBrochures = $sApi->findActiveBrochuresByCompany($singleCompany->getIdCompany());
        $aBrochuresByDate = array();
        $lastModified = '';

        foreach ($aBrochures as $singleBrochure)
        {
            if (strtotime($lastModified) < strtotime($singleBrochure['lastModified'])) {
                $lastModified = $singleBrochure['lastModified'];
            }

            $start = strtotime($singleBrochure['visibleFrom']);
            if (!$start)
            {
                $start = 0;
            }
            if (!strlen($singleBrochure['visibleFrom']))
            {
                $start = $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_modified'];
            }
            $end = strtotime($singleBrochure['validTo']);
            if (!$end)
            {
                $end = 0;
            }
            if (!array_key_exists($start, $aBrochuresByDate))
            {
                $aBrochuresByDate[$start] = array();
            }
            if (!array_key_exists($end, $aBrochuresByDate[$start]))
            {
                $aBrochuresByDate[$start][$end] = 0;
            }
            if (strtotime($singleBrochure['visibleTo']) > strtotime($singleBrochure['validTo']))
            {
                $end = strtotime($singleBrochure['visibleTo']);
            }
            $aBrochuresByDate[$start][$end] += 1;
        }

        if (count($aBrochures))
        {
            $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_import'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'brochure'));
            $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_modified'] = $lastModified;
            if ($lastModified < strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'brochure')))
            {
                $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_last_modified'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'brochure'));
            }
        }
        unset($aBrochures['lastModified']);

        $aCompanyInfos[$singleCompany->getIdCompany()]['brochures_count'] = $aBrochuresByDate;
    }

    if ($aCompanyInfos[$singleCompany->getIdCompany()]['products_check'])
    {
        $aCompanyInfos[$singleCompany->getIdCompany()]['products_count'] = 0;
        $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_modified'] = false;
        $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_import'] = false;

        $aArticles = $sApi->findActiveArticlesByCompany($singleCompany->getIdCompany());

        if (count($aArticles))
        {
            $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_import'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'article'));
            $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_modified'] = strtotime($aArticles['lastModified']);

            if (strtotime($aArticles['lastModified']) < strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'article')))
            {
                $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_modified'] = strtotime($sApi->findLastImport($singleCompany->getIdCompany(), 'article'));
            }

            unset($aArticles['lastModified']);
            $aArticlesByDate = array();

            foreach ($aArticles as $singleArticle)
            {
                $start = strtotime($singleArticle['visibleFrom']);
                if (!$start)
                {
                    $start = 0;
                }
                if (!strlen($singleArticle['visibleFrom']))
                {
                    $start = $aCompanyInfos[$singleCompany->getIdCompany()]['products_last_modified'];
                }
                $end = strtotime($singleArticle['validTo']);
                if (!$end)
                {
                    $end = 0;
                }
                if (!array_key_exists($start, $aArticlesByDate))
                {
                    $aArticlesByDate[$start] = array();
                }
                if (!array_key_exists($end, $aArticlesByDate[$start]))
                {
                    $aArticlesByDate[$start][$end] = 0;
                }
                if (strtotime($singleArticle['visibleTo']) > strtotime($singleArticle['validTo']))
                {
                    $end = strtotime($singleArticle['visibleTo']);
                }
                $aArticlesByDate[$start][$end] += 1;
            }
            $aCompanyInfos[$singleCompany->getIdCompany()]['products_count'] = $aArticlesByDate;
        }
    }
}

echo (serialize($aCompanyInfos));
