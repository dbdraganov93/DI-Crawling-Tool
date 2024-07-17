<?php

class Marktjagd_Service_Pinterest_ItemManager_Image implements Marktjagd_Service_Pinterest_ItemManager_PinItem
{
    public function createEntity(array $data): Marktjagd_Entity_Pinterest_Item
    {
        $image = new Marktjagd_Entity_Pinterest_Image($data['id']);
        $image->setTitle($data['title'])
            ->setSrc($data['src'])
            ->setUrl($data['url'])
            ->setCategory($data['category']);

        return $image;
    }

    public function toArray(Marktjagd_Entity_Pinterest_Item $image): ?array
    {
        if (!$image instanceof Marktjagd_Entity_Pinterest_Image) {
            return null;
        }

        return [
            'id' => $image->getId(),
            'title' => $image->getTitle(),
            'image' => $image->getSrc(),
            'link' => $image->getUrl(),
        ];
    }
}
