<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

abstract class Marktjagd_Entity_Pinterest_Item
{
    private string $id;

    protected string $category;

    protected string $type;

    protected string $itemContainerName;

    public function __construct(string $id, string $type = PinterestConfig::DEFAULT_ITEM_TYPE, string $itemContainerName = PinterestConfig::DEFAULT_ITEM_CONTAINER)
    {
        $this->id = $id;
        $this->type = $type;
        $this->itemContainerName = $itemContainerName;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getItemContainerName(): string
    {
        return $this->itemContainerName;
    }

    public function setCategory(string $category): Marktjagd_Entity_Pinterest_Item
    {
        $this->category = $category;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
