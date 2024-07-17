<?php

class Marktjagd_Entity_MarktjagdApi {
    /**
     * @param Marktjagd_Database_Entity_Partner $ePartner
     */
    public function setEnvironment($ePartner)
    {
        Marktjagd\ApiClient\Request\Request::setOptions(array(
            'host'             => $ePartner->getApiHost(),
            'key_id'           => $ePartner->getApiKey(),
            'secret_key'       => $ePartner->getApiPassword(),
            'ssl_verification' => false,
            'media_ssl'        => false
        ));
    }

    public function setEnvironmentByCompanyId($companyId)
    {
        $sPartner = new Marktjagd_Database_Service_Partner();
        $ePartner = $sPartner->findByCompanyId($companyId);
        $this->setEnvironment($ePartner);
    }
}