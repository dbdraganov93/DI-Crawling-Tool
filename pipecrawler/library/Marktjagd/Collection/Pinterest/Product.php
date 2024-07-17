<?php

/**
 * Pinterest Product Collection class
 */
class Marktjagd_Collection_Pinterest_Product
{
    /**
     * Elements of the Collection
     */
    protected array $products = [];

    /**
     * Add Product to the Collection
     */
    function addProduct(Marktjagd_Entity_Pinterest_Product $product): void
    {
        $productId = $product->getId();
        $this->products[$productId] = $product;
    }

    /**
     * Add multiple Products to the Collection
     *
     * @param array $products array of Marktjagd_Entity_Pinterest_Product
     */
    public function addProducts(array $products): void
    {
        foreach ($products as $product) {
            $this->addProduct($product);
        }
    }

    /**
     * Get all Products from the Collection
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * Clear all Products from the Collection
     */
    public function clearProducts(): void
    {
        $this->products = [];
    }

    /**
     * Remove a Product from the Collection
     */
    public function removeProduct(string $productId): void
    {
        if (array_key_exists($productId, $this->products)) {
            unset($this->products[$productId]);
        }
    }
}
