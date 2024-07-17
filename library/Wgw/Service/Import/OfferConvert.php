<?php

class Wgw_Service_Import_OfferConvert
{
    protected $_idCompany;

    public function __construct($idCompany)
    {
        $this->_idCompany = $idCompany;
    }

    public function convertEntity($eOffer)
    {
        if (is_a($eOffer, 'Marktjagd_Entity_Api_Brochure')) {
            return $this->_convertBrochure($eOffer);
        } elseif (is_a($eOffer, 'Marktjagd_Entity_Api_Article')) {
            return $this->_convertArticle($eOffer);
        } else {
            throw new Exception('invalid entity to convert.');
        }
    }

    /**
     * @param Marktjagd_Entity_Api_Article $eArticle
     * @return json $jArticleInfos
     */
    protected function _convertArticle($eArticle)
    {
        if (!$eArticle->getStart()) {
            $eArticle->setStart(date('d.m.Y', strtotime('now')));
        }
        if (!$eArticle->getVisibleStart()) {
            $eArticle->setVisibleStart($eArticle->getStart() . ' 00:00');
        }

        if (!$eArticle->getVisibleEnd()) {
            $eArticle->setVisibleEnd($eArticle->getEnd());
            $eArticle->setEnd(date('d.m.Y', strtotime($eArticle->getVisibleEnd() . '+1day')));

        }

        $validStart = new DateTime($eArticle->getStart());

        $visibleStart = new DateTime($eArticle->getVisibleStart());
        $visibleEnd = new DateTime($eArticle->getVisibleEnd());
        if ($eArticle->getEnd()) {
            $validEnd = new DateTime($eArticle->getEnd());
        }

        $aInfos['data'] = [
            'type' => 'offer',
            'attributes' => [
                'productName' => $eArticle->getTitle(),
                'description' => $eArticle->getText(),
                'price' => $eArticle->getPrice(),
                'originalPrice' => $eArticle->getSuggestedRetailPrice(),
                'priceLabel' => 1,
                'validFrom' => $validStart->format(DateTime::ATOM),
                'validTo' => $validEnd->format(DateTime::ATOM),
                'visibleFrom' => $visibleStart->format(DateTime::ATOM),
                'visibleTo' => $visibleEnd->format(DateTime::ATOM),
            ],
            'relationships' => [
                'company' => [
                    'data' => [
                        'type' => 'company',
                        'id' => $this->_idCompany
                    ]
                ],
                'category' => [
                    'data' => [
                        'type' => 'category',
                        'id' => 3
                    ]
                ]
            ]
        ];

        if (strlen($eArticle->getUrl())) {
            $aInfos['data']['links'] = [
                'self' => $eArticle->getUrl()
            ];
        }

        $jArticleInfos = json_encode($aInfos);

        return $jArticleInfos;
    }
}