<?php

/*
 * Store Crawler für XXXLutz (ID: 73436)
 */

class Crawler_Company_XxxLutzAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId): Crawler_Generic_Response
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $searchUrl = 'https://www.xxxlutz.at/api/graphql';
        $utm = '?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=filialen';

        foreach ($this->getStoreIds($companyId, $searchUrl, $sPage) as $storeId) {
            $data = '{"operationName":"getSubsidiary","variables":{"subsidiaryId":"' . $storeId . '"},"query":"query getSubsidiary($subsidiaryId: String) {\n  getPointOfServices(subsidiaryId: $subsidiaryId) {\n    pointOfServices {\n      seoData {\n        title\n        description\n        noIndex\n        noFollow\n        url\n        __typename\n      }\n      address {\n        fax\n        phone\n        email\n        firstName\n        lastName\n        postalCode\n        town\n        streetname\n        streetnumber\n        __typename\n      }\n      bigPicture {\n        altText\n        url\n        __typename\n      }\n      code\n      geoPoint {\n        latitude\n        longitude\n        __typename\n      }\n      longDescription\n      name\n      openingHoursText\n      services {\n        code\n        name\n        icon {\n          altText\n          url\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
            $jData = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getPointOfServices->pointOfServices[0];

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($jData->seoData->title)
                ->setWebsite($this->getUrl($searchUrl, $jData->seoData->url) . $utm)
                ->setFaxNormalized($jData->address->fax)
                ->setPhoneNormalized($jData->address->phone)
                ->setEmail($jData->address->email)
                ->setZipcode($jData->address->postalCode)
                ->setCity($jData->address->town)
                ->setStreet($jData->address->streetname)
                ->setStreetNumber($jData->address->streetnumber)
                ->setImage($jData->bigPicture->url)
                ->setStoreNumber($jData->code)
                ->setLatitude($jData->geoPoint->latitude)
                ->setLongitude($jData->geoPoint->longitude)
                ->setStoreHoursNormalized($jData->openingHoursText)
                ->setParking('vorhanden');

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores);
    }

    /**
     * @param string $companyId
     * @param string $searchUrl
     * @param Marktjagd_Service_Input_Page $sPage
     * @return array
     * @throws Exception
     */
    private function getStoreIds(string $companyId, string $searchUrl, Marktjagd_Service_Input_Page $sPage): array
    {
        $data = '{"operationName":"contentPages","variables":{"code":"filialen-standorte","cmsTicketId":null},"query":"query contentPages($code: String!, $cmsTicketId: String) {\n  getContentPage(code: $code, cmsTicketId: $cmsTicketId) {\n    uid\n    code\n    name\n    restType\n    title\n    name\n    seoData {\n      canonicalUrl\n      code\n      description\n      keywords\n      name\n      noFollow\n      noIndex\n      url\n      title\n      seoPath\n      __typename\n    }\n    cmsPageType {\n      code\n      type\n      __typename\n    }\n    breadcrumbs {\n      itemCode\n      name\n      restType\n      seoUrl\n      type\n      __typename\n    }\n    smartedit {\n      pageContract\n      __typename\n    }\n    contentSlots\n    __typename\n  }\n}\n"}';
        $jData = $sPage->getJsonFromGraphQL($searchUrl, $data);

        $aStoreIds = [];
        foreach ($jData->data->getContentPage->contentSlots[0]->components as $item) {
            if (!isset($item->components)) {
                continue;
            }
            foreach ($item->components as $component) {
                if (isset($component->link->url) && preg_match('#filiale.*\/(.+)$#i', $component->link->url, $match)) {
                    $aStoreIds[] = $match[1];
                }
            }
        }
        if (!$aStoreIds) {
            throw new Exception($companyId . ': unable to get any store detail urls.');
        }

        return $aStoreIds;
    }

    /**
     * @param $searchUrl
     * @param $storeUrl
     * @return string
     */
    private function getUrl($searchUrl, $storeUrl): string
    {
        $parsedUrl = parse_url($searchUrl);
        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $storeUrl;
    }
}
