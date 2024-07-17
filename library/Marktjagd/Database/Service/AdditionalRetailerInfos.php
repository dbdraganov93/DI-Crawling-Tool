<?php

class Marktjagd_Database_Service_AdditionalRetailerInfos extends Marktjagd_Database_Service_Abstract
{

    /**
     * Ermittelt alle verfügbaren Unternehmen
     * @return Marktjagd_Database_Collection_AdditionalRetailerInfos
     */
    public function findAll()
    {
        $cAdditionalRetailerInfos = new Marktjagd_Database_Collection_AdditionalRetailerInfos();
        $mAdditionalRetailerInfos = new Marktjagd_Database_Mapper_AdditionalRetailerInfos();

        $mAdditionalRetailerInfos->fetchAll(null, $cAdditionalRetailerInfos);
        
        return $cAdditionalRetailerInfos;
    }

    /**
     * Findet Eintrag anhand der ID
     * 
     * @param string $idStore
     * @return Marktjagd_Database_Entity_AdditionalRetailerInfos
     */
    public function find($idStore)
    {
        $eAdditionalRetailerInfos = new Marktjagd_Database_Entity_AdditionalRetailerInfos();
        $mAdditionalRetailerInfos = new Marktjagd_Database_Mapper_AdditionalRetailerInfos();

        $mAdditionalRetailerInfos->find($idStore, $eAdditionalRetailerInfos);

        return $eAdditionalRetailerInfos;
    }
    
    public function findAllStoreInfosByCompanyId($idCompany) {
        $cAdditionalRetailerInfos = new Marktjagd_Database_Collection_AdditionalRetailerInfos();
        $mAdditionalRetailerInfos = new Marktjagd_Database_Mapper_AdditionalRetailerInfos();

        $mAdditionalRetailerInfos->findAllStoreInfosByCompanyId($idCompany, $cAdditionalRetailerInfos);

        return $cAdditionalRetailerInfos;
    }
    
    public function deleteByStoreId($idStore)
    {
        $eAdditionalRetailerInfos = new Marktjagd_Database_Entity_AdditionalRetailerInfos();
        $mAdditionalRetailerInfos = new Marktjagd_Database_Mapper_AdditionalRetailerInfos();

        $mAdditionalRetailerInfos->deleteByStoreId($idStore, $eAdditionalRetailerInfos);

        return $eAdditionalRetailerInfos;
    }
}