<?php

interface Marktjagd_Service_Clickout_ClickoutInterface
{
    /**
     * @throws Exception
     */
    public function addClickout(string $pdf, string $url, string $localPath): string;
}