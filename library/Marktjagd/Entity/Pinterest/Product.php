<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

class Marktjagd_Entity_Pinterest_Product extends Marktjagd_Entity_Pinterest_Item
{
    /**
     * Product title
     */
    private string $title;

    /**
     * Product description
     */
    private string $text;

    /**
     * Product price
     */
    private string $price;

    /**
     * Product page URL
     */
    private string $url;

    /**
     * Product image URL
     */
    private string $imageUrl;

    public function __construct(string $id)
    {
        parent::__construct($id, PinterestConfig::ITEM_TYPE_PRODUCT, PinterestConfig::ITEM_CONTAINER_PRODUCT);
    }

    public function setTitle(string $title): Marktjagd_Entity_Pinterest_Product
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setText(string $text): Marktjagd_Entity_Pinterest_Product
    {
        $this->text = $text;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setPrice(string $price): Marktjagd_Entity_Pinterest_Product
    {
        $this->price = $price;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setUrl(string $url): Marktjagd_Entity_Pinterest_Product
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setImageUrl(string $imageUrl): Marktjagd_Entity_Pinterest_Product
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }
}
