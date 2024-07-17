<?php

/**
 * Pinterest Element Collection class
 */
class Marktjagd_Collection_Pinterest_Item
{
    /**
     * Items of the Collection
     */
    protected array $items = [];

    /**
     * Add Item to the Collection
     */
    function addItem(Marktjagd_Entity_Pinterest_Item $item): void
    {
        $itemId = $item->getId();
        $this->items[$itemId] = $item;
    }

    /**
     * Add multiple Items to the Collection
     *
     * @param array $items array of Marktjagd_Entity_Pinterest_Item
     */
    public function addItems(array $items): void
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    /**
     * Get all Items from the Collection
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Clear all vs from the Collection
     */
    public function clearItems(): void
    {
        $this->items = [];
    }

    /**
     * Remove an Element from the Collection
     */
    public function removeItem(string $itemId): void
    {
        if (array_key_exists($itemId, $this->items)) {
            unset($this->items[$itemId]);
        }
    }
}
