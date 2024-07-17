<?php

/*
 * Store Crawler für Europafoto (ID: 28984)
 */

class Crawler_Company_Europafoto_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://haendler.europafoto.de/';
        $searchUrl = $baseUrl . 'angebote/haendlersuche#map_canvas';
        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $aZip = $sGeo->findZipCodesByNetSize(40);
        $aStores = array();

        foreach ($aZip as $zipcode) {
            $postVars = array(
                'zip' => $zipcode,
                'zipsearch' => 'Suchen'
            );

            $sPage->open($searchUrl, $postVars);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="haendler\sclearfix"[^>]*>(.+?)</div>\s*</div>#';

            if (!preg_match_all($pattern, $page, $storeMatches)) {
                continue;
            }
            foreach ($storeMatches[1] as $storeMatch) {
                $eStore = new Marktjagd_Entity_Api_Store();
                // Storetitel
                $pattern = '#<div[^>]*class="beschreibung">\s*<h3[^>]*>(.+?)</h3>#';
                if (preg_match($pattern, $storeMatch, $match)) {
                    $eStore->setTitle($match[1]);
                }

                // Storeanschrift
                $pattern = '#<div[^>]*class="beschreibung">\s*<h3[^>]*>.+?</h3>\s*(.+?)\s*<br[^>]*>\s*(.+?)\s(.+?)\s*</div>#';
                if (!preg_match($pattern, $storeMatch, $match)) {
                    $this->_logger->err('couldn\'t find store address for store at zipcode ' . $zipcode);
                }

                $eStore->setStreetAndStreetNumber($match[1]);
                $eStore->setZipcode(str_pad($match[2],5, '0', STR_PAD_LEFT));
                $eStore->setCity($match[3]);

                $hashStore = md5($match[1] . $match[2] . $match[3]);
                $eStore->setStoreNumber(substr($hashStore, 0, 25));
                if (in_array($hashStore, $aStores)) {
                    continue;
                }

                $aStores[] = $hashStore;

                // Links suchen
                $pattern = '#(angebote/haendlersuche/detail/do/store/.+?)"#';
                if (preg_match($pattern, $storeMatch, $sLink)) {
                    $sPage->open($baseUrl . $sLink[1]);
                    $page = $sPage->getPage()->getResponseBody();

                    //Telefon
                    $pattern = '#Telefon\s(.+?)</p>#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setPhoneNormalized($match[1]);
                    }

                    // Telefax
                    $pattern = '#Fax\s(.+?)</p>#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setFaxNormalized($match[1]);
                    }

                    // Mail
                    $pattern = '#href="mailto:(.+?)"#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setEmail($match[1]);
                    }

                    // Website
                    $pattern = '#<a[^>]*target="_blank"[^>]*>(.+?)<#';
                    if (preg_match($pattern, $page, $match)) {
                        $match[1] = preg_replace('#^[w]{3}#', 'http://www', $match[1]);
                        if (preg_match('#http://www#', $match[1])) {
                            $eStore->setWebsite($match[1]);
                        }
                    }

                    // Öffnungszeiten
                    $pattern = '#ffnungszeiten(.+?)\s*</div>#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setStoreHoursNormalized(preg_replace('#bis#', '-', $match[1]));
                    }

                    // Logo
                    $pattern = '#<div[^>]*class="content-image"[^>]*>\s*<img[^>]*src="(.+?)"[^>]*>#';
                    if (preg_match($pattern, $page, $match)) {
                        $eStore->setLogo($match[1]);

                    }

                    switch ($eStore->getStoreNumber()) {
                        case '06645ed8781a882e61d6d8ea5':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/06645ed8781a882e61d6d8ea5.gif');
                            break;
                        case '0c7e27f46195cad9765dfc6f1':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/0c7e27f46195cad9765dfc6f1.png');
                            break;
                        case '2444430bde90b4d92fad3064c':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/2444430bde90b4d92fad3064c.png');
                            break;
                        case 'e8d5e93c833e7ad8551a8d40c':
                        case '31fd2ea08510577c370dbd229':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/e8d5e93c833e7ad8551a8d40c_31fd2ea08510577c370dbd229.png');
                            break;
                        case '19bc5377b08df906ce49ae56b':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/19bc5377b08df906ce49ae56b.png');
                            break;
                        case '1c042b0177114ad52f45e0486':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/1c042b0177114ad52f45e0486.jpg');
                            break;
                        case '47a98bca2bfb6c0df372516de':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/47a98bca2bfb6c0df372516de.png');
                            break;
                        case '1f5b15816141e6d46567b8608':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/1f5b15816141e6d46567b8608.png');
                            break;
                        case '30d9cc615392a5196e1595fc1':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/30d9cc615392a5196e1595fc1.png');
                            break;
                        case '24543dcb7697f3d74fc5b07f8':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/24543dcb7697f3d74fc5b07f8.png');
                            break;
                        case '2b47cf31c1ca0ab24d14d7020':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/2b47cf31c1ca0ab24d14d7020.png');
                            break;
                        case '1cc30c3781c2f6ae4895b9ce1':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/1cc30c3781c2f6ae4895b9ce1.png');
                            break;
                        case '0b7c9355a9a047ae4299c7b1b':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/0b7c9355a9a047ae4299c7b1b.png');
                            break;
                        case '3e3dc401b9c85bea14fba4fb9':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/3e3dc401b9c85bea14fba4fb9.jpg');
                            break;
                        case '12e0853f789418f7f4ef24350':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/12e0853f789418f7f4ef24350.png');
                            break;
                        case '0e4b8fb943fa13382ccce6ba1':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/0e4b8fb943fa13382ccce6ba1.png');
                            break;
                        case '20c59ca5ccc7ff621230b417b':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/20c59ca5ccc7ff621230b417b.png');
                            break;
                        case '462226cbf279fb815bc78b3a0':
                            $eStore->setLogo('https://di-gui.marktjagd.de/files/images/462226cbf279fb815bc78b3a0.png');
                            break;
                        default:
                            break;
                    }

                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
