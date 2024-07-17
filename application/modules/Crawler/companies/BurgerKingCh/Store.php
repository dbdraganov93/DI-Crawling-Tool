<?php

/**
 * Storecrawler für Burger King (ID: 72140)
 */
class Crawler_Company_BurgerKingCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://use1-prod-bk.rbictg.com/graphql',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'[{"operationName":"GetRestaurants","variables":{"input":{"filter":"NEARBY","coordinates":{"userLat":47.05016819999999,"userLng":8.3093072,"searchRadius":1000000},"first":1000,"status":"OPEN"}},"query":"query GetRestaurants($input: RestaurantsInput) {\\n  restaurants(input: $input) {\\n    pageInfo {\\n      hasNextPage\\n      endCursor\\n      __typename\\n    }\\n    totalCount\\n    nodes {\\n      ...RestaurantNodeFragment\\n      __typename\\n    }\\n    __typename\\n  }\\n}\\n\\nfragment RestaurantNodeFragment on RestaurantNode {\\n  _id\\n  storeId\\n  isAvailable\\n  posVendor\\n  chaseMerchantId\\n  curbsideHours {\\n    ...OperatingHoursFragment\\n    __typename\\n  }\\n  deliveryHours {\\n    ...OperatingHoursFragment\\n    __typename\\n  }\\n  diningRoomHours {\\n    ...OperatingHoursFragment\\n    __typename\\n  }\\n  distanceInMiles\\n  drinkStationType\\n  driveThruHours {\\n    ...OperatingHoursFragment\\n    __typename\\n  }\\n  driveThruLaneType\\n  email\\n  environment\\n  franchiseGroupId\\n  franchiseGroupName\\n  frontCounterClosed\\n  hasBreakfast\\n  hasBurgersForBreakfast\\n  hasCatering\\n  hasCurbside\\n  hasDelivery\\n  hasDineIn\\n  hasDriveThru\\n  hasMobileOrdering\\n  hasParking\\n  hasPlayground\\n  hasTakeOut\\n  hasWifi\\n  id\\n  isDarkKitchen\\n  isFavorite\\n  isRecent\\n  latitude\\n  longitude\\n  mobileOrderingStatus\\n  name\\n  number\\n  parkingType\\n  phoneNumber\\n  physicalAddress {\\n    address1\\n    address2\\n    city\\n    country\\n    postalCode\\n    stateProvince\\n    stateProvinceShort\\n    __typename\\n  }\\n  playgroundType\\n  pos {\\n    vendor\\n    __typename\\n  }\\n  posRestaurantId\\n  restaurantImage {\\n    asset {\\n      _id\\n      metadata {\\n        lqip\\n        palette {\\n          dominant {\\n            background\\n            foreground\\n            __typename\\n          }\\n          __typename\\n        }\\n        __typename\\n      }\\n      __typename\\n    }\\n    crop {\\n      top\\n      bottom\\n      left\\n      right\\n      __typename\\n    }\\n    hotspot {\\n      height\\n      width\\n      x\\n      y\\n      __typename\\n    }\\n    __typename\\n  }\\n  restaurantPosData {\\n    _id\\n    __typename\\n  }\\n  status\\n  vatNumber\\n  __typename\\n}\\n\\nfragment OperatingHoursFragment on OperatingHours {\\n  friClose\\n  friOpen\\n  monClose\\n  monOpen\\n  satClose\\n  satOpen\\n  sunClose\\n  sunOpen\\n  thrClose\\n  thrOpen\\n  tueClose\\n  tueOpen\\n  wedClose\\n  wedOpen\\n  __typename\\n}\\n"}]',
        CURLOPT_HTTPHEADER => [
            'authority: use1-prod-bk.rbictg.com',
            'x-ui-region: CH',
            'x-ui-language: de',
            'content-type: application/json',
            'accept: */*',
            'origin: https://www.burger-king.ch',
            'sec-fetch-site: cross-site',
            'accept-language: de,en-US;q=0.9,en;q=0.8'
        ],
    ]);

        $page = curl_exec($curl);
        curl_close($curl);

        $jStores = json_decode($page);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores[0]->data->restaurants->nodes as $jSingleStore) {
            $this->_logger->info("Store {$jSingleStore->storeId}: {$jSingleStore->name}");

            $storeHours =  $jSingleStore->diningRoomHours->monOpen? 'Mo ' . $jSingleStore->diningRoomHours->monOpen . '-' . $jSingleStore->diningRoomHours->monClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->tueOpen? ',Di ' . $jSingleStore->diningRoomHours->tueOpen . '-' . $jSingleStore->diningRoomHours->tueClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->wedOpen? ',Mi ' . $jSingleStore->diningRoomHours->wedOpen . '-' . $jSingleStore->diningRoomHours->wedClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->thrOpen? ',Do ' . $jSingleStore->diningRoomHours->thrOpen . '-' . $jSingleStore->diningRoomHours->thrClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->friOpen? ',Fr ' . $jSingleStore->diningRoomHours->friOpen . '-' . $jSingleStore->diningRoomHours->friClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->satOpen? ',Sa ' . $jSingleStore->diningRoomHours->satOpen . '-' . $jSingleStore->diningRoomHours->satClose : '';
            $storeHours .= $jSingleStore->diningRoomHours->sunOpen? ',So ' . $jSingleStore->diningRoomHours->sunOpen . '-' . $jSingleStore->diningRoomHours->sunClose : '';

            $storeDetailUrl = 'https://www.burger-king.ch/store-locator/store/' . trim($jSingleStore->_id);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($jSingleStore->physicalAddress->address1 , 'CH')
                #->setTitle($jSingleStore->name)
                ->setZipcode(str_replace("CH","",$jSingleStore->physicalAddress->postalCode))
                ->setCity($jSingleStore->physicalAddress->city)
                ->setEmail($jSingleStore->email)
                #->setPhone($jSingleStore->phoneNumber)
                ->setStoreNumber($jSingleStore->storeId)
                ->setStoreHoursNormalized($storeHours)
                ->setLatitude($jSingleStore->latitude)
                ->setLongitude($jSingleStore->longitude)
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}

function replace_unicode_escape_sequence($match)
{
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

function unicode_decode($str)
{
    return preg_replace_callback('#\\\\u([0-9a-f]{4})#i', 'replace_unicode_escape_sequence', $str);
}