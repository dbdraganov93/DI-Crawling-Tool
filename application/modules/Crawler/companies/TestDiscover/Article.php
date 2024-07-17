<?php

/**
 * Artikelcrawler für STAGE DI test (ID: 79902)
 */
class Crawler_Company_TestDiscover_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        # - This is a test crawler that should be used on STAGE ONLY!           #
        #                                                                       #
        # Use this crawler to create the Products and take note on the Range of #
        #   the imported Ids                                                    #
        #########################################################################

        $sFTP = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cArticles = new Marktjagd_Collection_Api_Article();

        // -- Create your own set of random products now!
        // -- Change here! --
        $numberOfArticles = 30;
        $articleNumberIdentifier = 'test0403';
        $startDate = '01.01.2022';
        $endDate = '01.12.2023';
        // -- Change here! --

        $localFolder = $sFTP->connect('testDi/images', true);

        $images = [];
        foreach ($sFTP->listFiles() as $singleFile) {
            // get images
            if (preg_match('#unsplash.jpg$#', $singleFile)) {
                $this->_logger->info('found image' . $singleFile);
                $images[] = $singleFile;
            }
        }

        $sFTP->close();

        $explodedLongText = explode('.', $this->getIpsumText());
        $explodedShortText = explode(' ', $this->getIpsumText());

        for ($i = 1; $i <= $numberOfArticles; $i++) {
            $ftpConfig = $sFTP->getMjFtpConfigNew();
            $parsedUrl = parse_url($images[rand(0, count($images))]);

            $imageUrl = 'ftp://'. $ftpConfig['username'] .':'. $ftpConfig['password'] . '@'. $ftpConfig['hostname'] .'/testDi/images/' . $parsedUrl['path'];

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle
                ->setArticleNumber($articleNumberIdentifier .'_'. rand(1,99999))
                ->setTitle($this->getRandomTextShard($explodedLongText, 32))
                ->setText($this->getRandomTextShard($explodedLongText))
                ->setPrice(rand(1,999))
                ->setUrl('https://www.'. $this->getRandomTextShard($explodedShortText) .'.com')
                ->setEan(rand(1,9999))
                ->setImage($imageUrl)
                ->setSuggestedRetailPrice(rand(1,9999))
                ->setStart($startDate)
                ->setVisibleStart($startDate)
                ->setEnd($endDate)
            ;

            $setAdditionalProps = rand(0, 1);
            if ($setAdditionalProps) {
                $additionalProps = [];
                $additionalProps['taxInfo'] = $this->getRandomTextShard($explodedLongText, 21);
                $additionalProps['energyLabel'] = 'A'; //TODO
                $additionalProps['energyLabelType'] = 'old'; //TODO
                $additionalProps['unitPrice'] = ['value' => rand(1,999), 'unit' => 'kg']; //TODO

                $eArticle->setAdditionalProperties(json_encode($additionalProps));
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function getRandomTextShard($array, $maxChars = null, $minChars = null): string
    {
        if (empty($maxChars) && empty($minChars)) {
            return trim($array[rand(1, count($array))]);
        }

        $randomText = $array[rand(1, count($array))];

        if ($maxChars < strlen($randomText) && $minChars > strlen($randomText)) {
            return $this->getRandomTextShard($array, $maxChars, $minChars);
        }

        return trim($randomText);
    }

    private function getIpsumText(): string
    {
        return 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris vulputate libero nulla, non efficitur nibh sagittis eu. Donec pulvinar feugiat ex, ut ultrices est vestibulum ac. Maecenas in rutrum metus. Nulla facilisis lorem vel lorem elementum, ultrices finibus erat facilisis. Sed porta eu quam non iaculis. Pellentesque tincidunt dui ipsum. Vestibulum a accumsan sapien. Nulla facilisi. Suspendisse rutrum libero id elit sodales commodo. Sed maximus, ex ut consectetur rhoncus, leo sapien aliquam nibh, quis luctus sapien leo sed ante. Vestibulum enim elit, semper sed facilisis ut, accumsan ut arcu. Phasellus vulputate risus quis purus tempus sodales.

Phasellus et imperdiet sem, et sodales sem. Cras rhoncus commodo turpis ut aliquam. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Vivamus eleifend quam sem, et bibendum lorem ullamcorper non. Integer sollicitudin, neque a accumsan laoreet, nisi sapien feugiat ex, ac pretium ex sapien quis leo. Sed sit amet efficitur lectus, non elementum neque. Mauris ut tempor quam. Integer auctor maximus felis ut tincidunt. Donec nulla odio, consequat at eros et, dictum vestibulum ipsum. Mauris tincidunt tempus nulla. Aliquam erat volutpat. Quisque aliquet, lectus ut eleifend auctor, nunc ipsum eleifend velit, sed rutrum velit massa eu ex. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Nunc scelerisque, neque eu ullamcorper viverra, sem nisl rhoncus elit, eu rhoncus mauris nibh in nisi.

Mauris urna orci, tristique vel diam a, laoreet egestas nunc. Nunc id eros sed nulla facilisis efficitur. Integer non mi sed nulla porttitor ultricies. Integer tempor ornare orci et imperdiet. Donec semper mollis turpis et bibendum. Sed rhoncus dapibus odio, sit amet tempus magna elementum non. Aliquam mattis, purus sed tempus vehicula, purus nisi tempus lectus, et porttitor nunc odio quis velit. Quisque aliquam arcu et condimentum malesuada. Curabitur quis consequat lacus. Pellentesque non dui eget nulla tempus egestas. In facilisis euismod dolor vel mollis. Morbi dictum, mauris et volutpat luctus, mi dui euismod enim, vitae lobortis purus tortor eu orci. Nulla posuere fermentum mi at vestibulum. Etiam molestie ex sit amet posuere laoreet.

Donec egestas, lorem vitae imperdiet convallis, massa mi hendrerit erat, ac tincidunt lacus enim ac erat. Mauris in porttitor eros, at suscipit leo. Nunc ligula ante, bibendum eu efficitur nec, aliquam vitae risus. In varius enim sit amet lectus tincidunt viverra. Sed ac nisi ut diam fermentum vestibulum rutrum non ante. Proin eu bibendum mi. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Praesent ac magna vitae metus euismod commodo at ut massa.

Nam blandit elit elit, sit amet dapibus eros iaculis vitae. Etiam imperdiet efficitur ipsum, sed hendrerit dui congue sit amet. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec suscipit magna a cursus semper. Etiam ultrices est sapien. Sed vel turpis eget tellus hendrerit malesuada non at nisi. Curabitur non nulla magna. Praesent luctus rutrum commodo. Sed consectetur bibendum elit a vulputate. Aliquam suscipit ullamcorper turpis a tincidunt. Sed eu mollis elit. Suspendisse non sem vel velit commodo porta. Vivamus ultrices purus metus, in gravida odio facilisis a.';
    }
}
