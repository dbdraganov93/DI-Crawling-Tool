<?php

class Marktjagd_Database_Service_CompanyAdditionalInfos extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle verfügbaren Unternehmensinfos
     * 
     * @return Marktjagd_Database_Collection_CompanyAdditionalInfos
     */
    public function findAll()
    {
        $cCompanyAdditionalInfos = new Marktjagd_Database_Collection_CompanyAdditionalInfos();
        $mCompanyAdditionalInfos = new Marktjagd_Database_Mapper_CompanyAdditionalInfos();
        $mCompanyAdditionalInfos->fetchAll(null, $cCompanyAdditionalInfos);
        
        return $cCompanyAdditionalInfos;
    }
    
    /**
     * Ermittelt Zusatzinformationen anhand der CompanyID
     * 
     * @param string $companyId
     * @return Marktjagd_Database_Entity_CompanyAdditionalInfos
     */
    public function findByCompanyId($companyId) {
        $eCompanyAdditionalInfos = new Marktjagd_Database_Entity_CompanyAdditionalInfos();
        $mCompanyAdditionalInfos = new Marktjagd_Database_Mapper_CompanyAdditionalInfos();
        $mCompanyAdditionalInfos->findByCompanyId($companyId, $eCompanyAdditionalInfos);
        
        return $eCompanyAdditionalInfos;
    }
}