<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

class Marktjagd_Service_Pinterest_ItemFactory
{
    private array $entityCreators = [
        PinterestConfig::ITEM_TYPE_PRODUCT => Marktjagd_Service_Pinterest_ItemManager_Product::class,
        PinterestConfig::ITEM_TYPE_IMAGE => Marktjagd_Service_Pinterest_ItemManager_Image::class,
    ];

    public function createItem(array $data, string $type = PinterestConfig::DEFAULT_ITEM_TYPE): ?Marktjagd_Entity_Pinterest_Item
    {
        $manager = $this->getEntityManager($type);

        return $manager->createEntity($data);
    }

    public function parseItem(Marktjagd_Entity_Pinterest_Item $item): ?array
    {
        $itemType = $item->getType();
        $manager = $this->getEntityManager($itemType);

        return $manager->toArray($item);
    }

    private function getEntityManager(string $type): ?Marktjagd_Service_Pinterest_ItemManager_PinItem
    {
        $creatorClass = $this->entityCreators[$type];
        if (empty($creatorClass)) {
            throw new Exception("Item of type {$type} could not be created!");
        }

        return new $creatorClass();
    }
}
