<?php

class Marktjagd_Service_Pinterest_Config
{
    // The default number of products per category
    public const DEFAULT_PRODUCTS_PER_CATEGORY = 4;

    // The default maximum number of categories
    public const DEFAULT_MAX_CATEGORIES = 4;

    // item types
    public const ITEM_TYPE_PRODUCT = 'product';
    public const ITEM_TYPE_IMAGE = 'image';

    public const DEFAULT_ITEM_TYPE = self::ITEM_TYPE_PRODUCT;

    // item container property name inside a category
    public const ITEM_CONTAINER_PRODUCT = 'products';
    public const ITEM_CONTAINER_IMAGE = 'images';

    public const DEFAULT_ITEM_CONTAINER = self::ITEM_CONTAINER_PRODUCT;
}
