<?php

class Marktjagd_Service_Publitas_Publications
{
    private const API_KEY = 'X9xpwXJ5yRao3HztbOgDL6rGKUV8wGjC1zz0eh80';
    private const PUBLICATIONS_URL = 'https://affiliate.publitas.com/publications';
    private const GRAPHQL_URL = 'https://affiliate.publitas.com/graphql';
    private const GRAPHQL_PARAMS_ID_PATTERN = 'id: "%s"';
    private const GRAPHQL_PARAMS_SLUG_PATTERN = 'groupSlug: "%s", slug: "%s"';

    public function getPublicationsFromAPI(int $companyId, int $accountID = 0): ?array
    {
        $ch = curl_init(self::PUBLICATIONS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=UTF-8',
            'User-Agent: Offerista-Suchdienst (+https://www.offerista.com/suchdienst/)',
            'x-api-key: ' . self::API_KEY,
            ),
        );

        $curl_response = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = json_decode($curl_response);

        if (!is_object($response)) {
            curl_close($ch);
            throw new Exception("Company ID: $companyId: Couldn't get publications from API (Code: $responseCode): " . $response);
        }
        curl_close($ch);

        return array_filter($response->publications, function($publication) use ($accountID) {
            $forTheSpecificClient = (0 === $accountID) || ($publication->accountId === $accountID);
            return $this->isValid($publication) && $forTheSpecificClient;
        });
    }

    private function isValid(object $publication): bool
    {
        return (!empty($publication->scheduleOnlineAt) && !empty($publication->scheduleOfflineAt))
                && strtotime('now') < strtotime($publication->scheduleOfflineAt);
    }

    public function getPublicationDataByID(int $companyId, int $publicationId): array
    {
        $params = sprintf(self::GRAPHQL_PARAMS_ID_PATTERN, $publicationId);
        $response = $this->callGraphQL($params);

        return $this->getPublicationData($companyId, $response);
    }

    public function getPublicationDataBySlug(int $companyId, string $groupSlug, string $slug): array
    {
        $params = sprintf(self::GRAPHQL_PARAMS_SLUG_PATTERN, $groupSlug, $slug);
        $response = $this->callGraphQL($params);

        return $this->getPublicationData($companyId, $response);
    }

    private function callGraphQL(string $params): object
    {
        $postParams = [
            "operationName" => "Publication",
            "query" => "query Publication {
                    publication(" . $params . ") {
                        spreads {
                            nodes {
                                id
                                position
                                publicationId
                                hotspots {
                                    left
                                    top
                                    ... on ProductHotspot {
                                        left
                                        top
                                        height
                                        width
                                        products {
                                            webshopIdentifier
                                            webshopUrl
                                        }
                                    }
                                    ... on ExternalLinkHotspot {
                                        left
                                        top
                                        height
                                        width
                                        url
                                    }
                                }
                                pages {
                                    height
                                    position
                                    width
                                }
                            }
                        }
                        description
                        downloadPdfUrl
                        groupSlug
                        id
                        slug
                        title
                        layout
                        offlineAt
                        onlineAt
                        scheduleOfflineAt
                        scheduleOnlineAt
                        validFrom
                    }
                }"
        ];

        $ch = curl_init(self::GRAPHQL_URL);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'User-Agent: Offerista-Suchdienst (+https://www.offerista.com/suchdienst/)',
            'Content-Length: ' . strlen(json_encode($postParams)),
            'x-api-key: ' . self::API_KEY),
        );


        $curl_response = curl_exec($ch);

        if (!is_object($jResponse = json_decode($curl_response))) {
            curl_close($ch);
            throw new Exception("GraphQL Call not possible: $curl_response");
        }
        curl_close($ch);

        return $jResponse->data->publication;
    }

    private function getPublicationData(int $companyId, object $publication): array
    {
        $localBrochurePath = $this->downloadBrochure($companyId, $publication->downloadPdfUrl);
        $brochureWithClickouts = $this->attachClickouts($localBrochurePath, $publication);

        return [
            'title' => $publication->publicationTitle,
            'url' => $brochureWithClickouts,
            'number' => $publication->id . '_' . $publication->slug,
            'start' => $publication->scheduleOnlineAt,
            'end' => $publication->scheduleOfflineAt
        ];
    }

    private function downloadBrochure(int $companyId, string $publicationUrl): string
    {
        $http = new Marktjagd_Service_Transfer_Http();
        $localPath = $http->generateLocalDownloadFolder($companyId);

        return $http->getRemoteFile($publicationUrl, $localPath);
    }

    private function attachClickouts(string $brochurePath, object $publicationData): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $previousPage = -1;
        $clickouts = [];
        foreach ($publicationData->spreads->nodes as $node) {
            foreach ($node->hotspots as $hotspot) {
                if (count($node->pages) == 2) {
                    $pageIndex = $hotspot->left > 0.5 ? 1 : 0;
                    $page = $previousPage + 1 + $pageIndex;
                    $pageData = $node->pages[$pageIndex];
                    $layout = 'double';
                } else {
                    $page = $previousPage + 1;
                    $pageData = $node->pages[0];
                    $layout = 'single';
                }

                $clickout = $this->generateClickout($hotspot, $pageData, $page, $layout);
                if (empty($clickout)) {
                    continue;
                }

                $clickouts[] = $clickout;
            }

            $previousPage += count($node->pages);
        }

        // assign the clickouts to the brochure
        $jsonFile = dirname($brochurePath) .'clickouts.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($clickouts));
        fclose($fh);

        return $pdfService->setAnnotations($brochurePath, $jsonFile);
    }

    private function generateClickout(object $hotspotData, object $pageData, int $page, string $layout): array
    {
        if (isset($hotspotData->products)) {
            $url = $hotspotData->products[0]->webshopUrl;
        }
        if (isset($hotspotData->url)) {
            $url = $hotspotData->url;
        }

        if (!isset($url)) {
            return [];
        }

        $url = preg_replace(['#http%3A#', '#http:#'], ['https%3A', 'https:'], $url);

        if ('double' == $layout) {
            if ($hotspotData->left > 0.5) {
                $hotspotData->left = $hotspotData->left - 0.5;

                $endXPercent = $hotspotData->width * 2;
            }
            else {
                $whole = $hotspotData->left + $hotspotData->width;
                if ($whole > 1) {
                    $endXPercent = ($whole - $hotspotData->left) * 2;
                }
                else {
                    $endXPercent = $hotspotData->width * 2;
                }
            }
            $startX = $pageData->width * ($hotspotData->left * 2);
            $endX = $startX + ($pageData->width * $endXPercent);
        }
        else {
            $startX = $pageData->width * $hotspotData->left;
            $endX = $startX + ($pageData->width * $hotspotData->width);
        }
        if ($endX > $pageData->width) {
            $endX = $pageData->width;
        }

        $startY = $pageData->height * $hotspotData->top;
        $endY = $startY + ($pageData->height * $hotspotData->height);
        $startY = $pageData->height - $startY;
        $endY = $pageData->height - $endY;

        return [
            'page' => $page,
            'height' => $pageData->height,
            'width' => $pageData->width,
            'startX' => $startX,
            'endX' => $endX,
            'startY' => $startY,
            'endY' => $endY,
            'link' => $url
        ];
    }
}
