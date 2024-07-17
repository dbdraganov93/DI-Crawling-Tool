<?php

class Shopfully_Service_BrochureApi
{
    private Shopfully_Service_ApiClient $shopfullyApiClient;
    private Shopfully_Service_StoreApi $storeApi;
    private Shopfully_Mapper_BrochureMapper $brochureMapper;
    private Shopfully_Mapper_ClickoutMapper $clickoutMapper;

    public function __construct(string $lang)
    {
        $this->shopfullyApiClient = new Shopfully_Service_ApiClient($lang);
        $this->storeApi = new Shopfully_Service_StoreApi($lang);
        $this->brochureMapper = new Shopfully_Mapper_BrochureMapper();
        $this->clickoutMapper = new Shopfully_Mapper_ClickoutMapper();
    }

    /**
     * With this method, we get the brochures data from the Shopfully API that start today.
     */
    public function getBrochuresThatStartToday(): array
    {
        $page = 1;
        $brochures = [];
        do {
            $brochuresData = $this->shopfullyApiClient->getBrochuresThatStartAt(date('Y-m-d'), $page);
            foreach ($brochuresData as $brochureData) {
                $brochure = $this->brochureMapper->toEntity($brochureData['Flyer']);

                $this->setBrochureImages($brochure);

                // Skip the brochure if we don't have any images for it.
                if (null === $brochure->getImages()) {
                    continue;
                }

                $this->setBrochureStores($brochure);
                $this->setBrochureClickout($brochure);

                $brochures[] = $brochure;
            }
            $page++;
        } while ($brochuresData);

        return $brochures;
    }

    /**
     * With this method, we get the brochures data from the Shopfully API for specific customer.
     */
    public function getBrochures(int $customerId, bool $includeStores = true, bool $includeClickouts = true): array
    {
        $page = 1;
        $brochures = [];
        do {
            $brochuresData = $this->shopfullyApiClient->getClientBrochures($customerId, $page);

            foreach ($brochuresData as $brochureData) {
                $brochure = $this->brochureMapper->toEntity($brochureData['Flyer']);

                $this->setBrochurePdf($brochure);

                if ($includeStores) {
                    $this->setBrochureStores($brochure);
                }

                if ($includeClickouts) {
                    $this->setBrochureClickout($brochure);
                }
                $brochures[] = $brochure;
            }
            $page++;
        } while ($brochuresData);

        return $brochures;
    }

    /**
     * With this method, we get the brochure data from the Shopfully API
     */
    public function getBrochure(int $brochureId): ?Shopfully_Entity_Brochure
    {
        $brochureData = $this->shopfullyApiClient->getBrochure($brochureId);
        $brochureData = reset($brochureData)['Flyer'];
        $brochure = $this->brochureMapper->toEntity($brochureData);

        $this->setBrochurePdf($brochure);
        $this->setBrochureStores($brochure);
        $this->setBrochureClickout($brochure);
        $this->setBrochureImages($brochure);

        return $brochure;
    }

    /**
     * With this method, we get the brochure clickout data from the Shopfully API
     */
    public function getBrochureClickout(int $brochureId): ?array
    {
        $brochureClickouts = [];
        $brochureClickoutsData = $this->shopfullyApiClient->getBrochureClickout($brochureId);

        if ($brochureClickoutsData) {
            foreach ($brochureClickoutsData as $brochureClickoutData) {
                if ($this->isClickout($brochureClickoutData['FlyerGib'])) {
                    $brochureClickout = $this->clickoutMapper->toEntity($brochureClickoutData['FlyerGib']);
                    $brochureClickouts[] = $brochureClickout;
                }
            }
        }

        return $brochureClickouts;
    }

    /**
     * Shopfully has three types of clickouts, and we can integrate only two of the types.
     * This function checks if the clickout is one of the two types that we can integrate.
     */
    private function isClickout(array $data): bool
    {
        return !empty($data['settings']['external_url']) || !empty($data['settings']['layout']['button'][0]['attributes']['href']) || !empty($data['settings']['layout']['buttons'][0]['attributes']['href']);
    }

    /**
     * Get all stores from the brochure and set store IDs to the brochure entity.
     */
    private function setBrochureStores(Shopfully_Entity_Brochure $brochure): void
    {
        // To get the stores we need to call another endpoint
        $stores = $this->storeApi->getStoresIdsByBrochureId($brochure->getId());
        if (!empty($stores)) {
            $brochure->setStores($stores);
        }
    }

    /**
     * With this method, we get the PDF URL and the number of pages from the Shopfully API and set them to the brochure entity.
     */
    private function setBrochurePdf(Shopfully_Entity_Brochure $brochure): void
    {
        // To get the pdf url we need to call another endpoint
        $publication = $this->shopfullyApiClient->getBrochurePdf($brochure->getId());
        $publication = reset($publication);

        // If the pdf url is not empty, we set it to the brochure
        if (!empty($publication['Publication']['pdf_url'])) {
            $brochure->setPdfUrl($publication['Publication']['pdf_url']);
        }

        // If the number of pages is not empty, we set it to the brochure
        if (!empty($publication['Publication']['settings']['number_of_pages'])) {
            $brochure->setNumberOfPages($publication['Publication']['settings']['number_of_pages']);
        }
    }

    /**
     * With this method, we get the images from the Shopfully API and set them to the brochure entity.
     */
    private function setBrochureImages(Shopfully_Entity_Brochure $brochure): void
    {
        // To get the pdf url we need to call another endpoint
        $publication = $this->shopfullyApiClient->getBrochurePdf($brochure->getId());
        if (empty($publication)) {
            return;
        }

        $publication = reset($publication);
        $page = 1;
        $images = [];
        do {
            $imagesData = $this->shopfullyApiClient->getPublicationImages($publication['Publication']['id'], $page);
            if ($imagesData) {
                foreach ($imagesData as $imageData) {
                    $images[$imageData['PublicationPageAsset']['page_number'] - 1] = $imageData['PublicationPageAsset']['public_url'];
                }
            }

            $page++;
        } while ($imagesData);

        ksort($images);
        $brochure->setNumberOfPages(count($images));
        $brochure->setImages($images);
    }

    /**
     * With this method, we get the clickouts from the Shopfully API and set them to the brochure entity.
     */
    private function setBrochureClickout(Shopfully_Entity_Brochure $brochure): void
    {
        // To get the clickouts we need to call another endpoint
        $clickouts = $this->getBrochureClickout($brochure->getId());
        if (!empty($clickouts)) {
            $brochure->setClickouts($clickouts);
        }
    }
}
