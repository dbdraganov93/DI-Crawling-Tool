<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

class Marktjagd_Entity_Pinterest_Image extends Marktjagd_Entity_Pinterest_Item
{
    /**
     * Image title
     */
    private string $title;

    /**
     * Image source URL
     */
    private string $src;

    /**
     * Image page URL
     */
    private string $url;

    public function __construct(string $id)
    {
        parent::__construct($id, PinterestConfig::ITEM_TYPE_IMAGE, PinterestConfig::ITEM_CONTAINER_IMAGE);
    }

    public function setTitle(string $title): Marktjagd_Entity_Pinterest_Image
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setSrc(string $imageUrl): Marktjagd_Entity_Pinterest_Image
    {
        $this->src = $imageUrl;
        return $this;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function setUrl(string $url): Marktjagd_Entity_Pinterest_Image
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
