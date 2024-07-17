<?php

class Marktjagd_Database_Service_AmountStores extends Marktjagd_Database_Service_Abstract {

    /**
     * 
     * @param string $companyId
     * @return Marktjagd_Database_Entity_AmountStores
     */
    public function findByCompanyId($companyId) {
        $eStores = new Marktjagd_Database_Entity_AmountStores();
        $mStores = new Marktjagd_Database_Mapper_AmountStores();

        $mStores->findByCompanyId($companyId, $eStores);
        
        return $eStores;
    }
    
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate) {
        $cStores = new Marktjagd_Database_Collection_AmountStores();
        $mStores = new Marktjagd_Database_Mapper_AmountStores();

        $mStores->findByCompanyIdAndTime($companyId, $startDate, $endDate, $cStores);
        
        return $cStores;
    }

    public function findLatestState() {
        $cStores = new Marktjagd_Database_Collection_AmountStores();
        $mStores = new Marktjagd_Database_Mapper_AmountStores();
        $mStores->findLatestState($cStores);
        
        return $cStores;
    }

}
