<?php

class Marktjagd_Database_Service_AmountProducts extends Marktjagd_Database_Service_Abstract {
    
    public function findLatestState($companyId) {
        $eProducts = new Marktjagd_Database_Entity_AmountProducts();
        $mProducts = new Marktjagd_Database_Mapper_AmountProducts();
        $mProducts->findLatestState($companyId, $eProducts);
        
        return $eProducts;
    }
    
    public function findByCompanyIdAndTime($companyId, $startDate, $endDate) {
        $cProducts = new Marktjagd_Database_Collection_AmountProducts();
        $mProducts = new Marktjagd_Database_Mapper_AmountProducts();

        $mProducts->findByCompanyIdAndTime($companyId, $startDate, $endDate, $cProducts);
        
        return $cProducts;
    }
}