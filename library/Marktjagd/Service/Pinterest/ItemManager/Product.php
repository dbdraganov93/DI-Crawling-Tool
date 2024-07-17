<?php

class Marktjagd_Service_Pinterest_ItemManager_Product implements Marktjagd_Service_Pinterest_ItemManager_PinItem
{
    public function createEntity(array $data): Marktjagd_Entity_Pinterest_Item
    {
        $product = new Marktjagd_Entity_Pinterest_Product($data['id']);
        $product->setTitle($data['title'])
            ->setText($data['text'])
            ->setPrice($data['price'])
            ->setUrl($data['url'])
            ->setImageUrl($data['imageUrl'])
            ->setCategory($data['category']);

        return $product;
    }

    public function toArray(Marktjagd_Entity_Pinterest_Item $product): ?array
    {
        if (!$product instanceof Marktjagd_Entity_Pinterest_Product) {
            return null;
        }

        return [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'text' => $product->getText(),
            'price' => $product->getPrice(),
            'url' => $product->getUrl(),
            'image_url' => $product->getImageUrl()
        ];
    }
}
