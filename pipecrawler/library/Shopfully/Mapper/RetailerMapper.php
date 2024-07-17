<?php
require_once APPLICATION_PATH . '/../vendor/autoload.php';

class Shopfully_Mapper_RetailerMapper
{
    public function toEntity(array $data): Shopfully_Entity_Retailer
    {
        $retailer = new Shopfully_Entity_Retailer();
        $retailer->setId($data['id'] ?? null);
        $retailer->setCategoryId($data['category_id'] ?? null);
        $retailer->setName($data['name'] ?? null);
        $retailer->setSlug($data['slug'] ?? '');
        $retailer->setGroup($data['group'] ?? null);
        $retailer->setDescription($data['description'] ?? null);
        $retailer->setUrl($data['url'] ?? null);

        return $retailer;
    }
}
