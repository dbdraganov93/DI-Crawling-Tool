<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

class Marktjagd_Service_Pinterest_PinExtension
{
    /**
     * Pattern for the temporary JSON file path
     */
    private const TEMP_JSON_FILE_PATH_PATTERN = APPLICATION_PATH . '/../public/files/tmp/%s_pin_extension_%s.json';

    /**
     * Pattern for the S3 JSON directory
     */
    private const S3_JSON_DIRECTORY_PATTERN = '/%s_%s/';

    /**
     * Pattern for the S3 JSON file name
     */
    private const S3_JSON_FILE_NAME_PATTERN = 'pin_extension_%s.json';


    private int $maxCategories;
    private int $itemsPerCategory;

    public function __construct(int $maxCategories = PinterestConfig::DEFAULT_MAX_CATEGORIES, int $productsPerCategory = PinterestConfig::DEFAULT_PRODUCTS_PER_CATEGORY)
    {
        $this->setMaxCategories($maxCategories);
        $this->setItemsPerCategory($productsPerCategory);
    }

    public function setMaxCategories(int $maxCategories): void
    {
        $this->maxCategories = $maxCategories;
    }

    public function setItemsPerCategory(int $itemsPerCategory): void
    {
        $this->itemsPerCategory = $itemsPerCategory;
    }

    public function createPin(int $companyId, array $pinData): Marktjagd_Entity_Pinterest_Pin
    {
        if (empty($pinData['pinNumber'])) {
            throw new Exception("Company ID: {$companyId}: Pin number is not set");
        }

        if (empty($pinData['companyName'])) {
            throw new Exception("Company ID: {$companyId}: Company name is not set");
        }

        $pin = new Marktjagd_Entity_Pinterest_Pin($pinData['companyName'], $companyId, $pinData['pinNumber']);

        if (!empty($pinData['cover'])) {
            $pin->setCoverURL($pinData['cover']);
        }
        if (!empty($pinData['coverClickout'])) {
            $pin->setCoverClickout($pinData['coverClickout']);
        }

        if (!empty($pinData['ctaText'])) {
            $pin->setCTAText($pinData['ctaText']);
        }

        if (!empty($pinData['ctaUrl'])) {
            $pin->setCTAUrl($pinData['ctaUrl']);
        }

        return $pin;
    }

    public function createPinItem(array $itemData, string $type = ''): ?Marktjagd_Entity_Pinterest_Item
    {
        $itemFactory = new Marktjagd_Service_Pinterest_ItemFactory();

        if (!isset($itemData['id'])) {
            throw new Exception("Item ID is not set: " . json_encode($itemData));
        }

        return $itemFactory->createItem($itemData, $type);
    }

    public function generateAndUploadJSON(Marktjagd_Entity_Pinterest_Pin $pin, Marktjagd_Collection_Pinterest_Item $pinItems): void
    {
        $jsonFile = $this->generateJSONFile($pin, $pinItems);
        $this->saveToAWS($jsonFile, $pin);
    }

    public function generateJSONFile(Marktjagd_Entity_Pinterest_Pin $pin, Marktjagd_Collection_Pinterest_Item $pinItems): string
    {
        $categories = $this->getCategories($pinItems, $pin->getCategoryOrder());
        $coverType = $this->getCoverType($pin->getCoverUrl());

        $json = [
            'header' => [
                $coverType => $pin->getCoverUrl(),
                'link' => $pin->getCoverClickout()
            ],
            'map' => [
                'search' => $pin->getSearchTerm()
            ],
            'cta' => [
                'text' => $pin->getCTAText(),
                'link' => $pin->getCTAUrl()
            ],
            'categories' => $this->parseCategories($categories)
        ];

        $content = json_encode($json);
        $filePath = sprintf(self::TEMP_JSON_FILE_PATH_PATTERN, $pin->getCompanyId(), $pin->getPinNumber());
        $fh = fopen($filePath, 'w+');
        fwrite($fh, $content);
        fclose($fh);

        return $filePath;
    }

    private function getCategories(Marktjagd_Collection_Pinterest_Item $items, string $categoryOrder = ''): array
    {
        $selectedCategories = $categoryOrder ? preg_split('#,\s*#', $categoryOrder) : [];

        $categories = [];
        foreach ($items->getItems() as $item) {
            $category = $item->getCategory();

            if (!empty($selectedCategories) && !in_array($category, $selectedCategories)) {
                continue;
            }

            if (!isset($categories[$category]) || $this->itemsPerCategory > count($categories[$category])) {
                if (!empty($categories[$category])) {
                    $firstItem = reset($categories[$category]);
                    if ($firstItem->getType() !== $item->getType()) {
                        // only 1 type of items can be in a category

                        if (PinterestConfig::DEFAULT_ITEM_TYPE === $item->getType()) {
                            // the default category is with priority, so it should be replaced
                            $categories[$category] = [$item];
                        }

                        continue;
                    }
                }

                $categories[$category][] = $item;
            }
        }

        return array_slice($categories, 0, $this->maxCategories, true);
    }

    private function getCoverType(string $coverURL): string
    {
        $coverType = 'image';
        if (preg_match('#\.mp4$#', $coverURL)) {
            $coverType = 'video';
        }

        return $coverType;
    }

    private function parseCategories(array $categories): array
    {
        $parsedCategories = [];
        $categoryId = 0;
        foreach ($categories as $category => $items) {
            $categoryId++;
            $firstItem = reset($items);
            $itemsContainer = $firstItem->getItemContainerName();
            $parsedCategories[] = [
                'id' => $categoryId,
                'name' => $category,
                $itemsContainer => $this->parseItems($items)
            ];
        }

        return $parsedCategories;
    }

    private function parseItems(array $items): array
    {
        $itemFactory = new Marktjagd_Service_Pinterest_ItemFactory();

        $parsedItems = [];
        foreach ($items as $item) {
            $parsedItems[] = $itemFactory->parseItem($item);
        }

        return $parsedItems;
    }

    public function saveToAWS(string $jsonFile, Marktjagd_Entity_Pinterest_Pin $pin): string
    {
        $s3FilePath = sprintf(self::S3_JSON_DIRECTORY_PATTERN, $pin->getCompanyId(), $pin->getCompanyName());
        $s3FileName = sprintf(self::S3_JSON_FILE_NAME_PATTERN, $pin->getPinNumber());
        $s3File = new Marktjagd_Service_Output_S3File($s3FilePath, $s3FileName, true);

        return $s3File->saveFileInS3($jsonFile);
    }

    public function deletePinJSON(int $companyId, string $companyName, string $pinNumber): ?object
    {
        if (empty($pinNumber)) {
            $logger = Zend_Registry::get('logger');
            $logger->err("Company ID: {$companyId}: No pin number provided for the JSON file deletion.");
            
            return null;
        }

        $s3FilePath = sprintf(self::S3_JSON_DIRECTORY_PATTERN, $companyId, $companyName);
        $s3FileName = sprintf(self::S3_JSON_FILE_NAME_PATTERN, $pinNumber);
        $s3File = new Marktjagd_Service_Output_S3File($s3FilePath, $s3FileName, true);
        $fileUrl = preg_replace('#^/#', '', $s3FilePath . $s3FileName);

        return $s3File->removeFileFromBucket($fileUrl);
    }

    public function campaignIsActive(string $start, string $end = ''): bool
    {
        $now = strtotime('now');
        $start = strtotime($start);

        return $now >= $start && (empty($end) || $now < strtotime($end));
    }
}
