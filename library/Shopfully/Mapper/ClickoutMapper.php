<?php

class Shopfully_Mapper_ClickoutMapper
{
    public function toEntity(array $data): Shopfully_Entity_Clickout
    {
        $clickout = new Shopfully_Entity_Clickout();
        $clickout->setClickout($data['settings']['external_url'] ?? $data['settings']['layout']['button'][0]['attributes']['href'] ?? $data['settings']['layout']['buttons'][0]['attributes']['href']);
        $clickout->setPageNumber($data['settings']['flyer_page'] ?? '');
        $clickout->setWidth($data['settings']['pin']['shape']['width'] ?? '');
        $clickout->setHeight($data['settings']['pin']['shape']['height'] ?? '');
        $clickout->setX($data['settings']['pin']['shape']['x'] ?? '');
        $clickout->setY($data['settings']['pin']['shape']['y'] ?? '');

        return $clickout;
    }
}
