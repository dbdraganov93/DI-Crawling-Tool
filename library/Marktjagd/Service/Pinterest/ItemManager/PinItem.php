<?php

interface Marktjagd_Service_Pinterest_ItemManager_PinItem
{
    public function createEntity(array $data): Marktjagd_Entity_Pinterest_Item;
    public function toArray(Marktjagd_Entity_Pinterest_Item $item): ?array;
}
