<?php

/*
 * Class Marktjagd_Database_Service_QualityCheckErrors
 */

class Marktjagd_Database_Service_QualityCheckErrors extends Marktjagd_Database_Service_Abstract {

    /**
     * Findet alle QA-Errors
     * 
     * @return Marktjagd_Database_Collection_QualityCheckErrors
     */
    public function findAll() {
        $cQualityCheckErrors = new Marktjagd_Database_Collection_QualityCheckErrors();
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();
        
        $mQualityCheckErrors->fetchAll(null, $cQualityCheckErrors);
        
        return $cQualityCheckErrors;
    }

    /**
     * Findet anhand der Fehler-ID den spezifischen Fehler
     * 
     * @param string $idQualityCheckErrors
     * @return Marktjagd_Database_Entity_QualityCheckErrors
     */
    public function find($idQualityCheckErrors) {
        $eQualityCheckErrors = new Marktjagd_Database_Entity_QualityCheckErrors();
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();
        
        $mQualityCheckErrors->find($idQualityCheckErrors, $eQualityCheckErrors);

        return $eQualityCheckErrors;
    }

    /**
     * Findet alle aktuellen Fehler für ein Unternehmen
     * 
     * @param string $idCompany
     * @return Marktjagd_Database_Collection_QualityCheckErrors
     */
    public function findByCompanyId($idCompany) {
        $cQualityCheckErrors = new Marktjagd_Database_Collection_QualityCheckErrors();
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();
        
        $mQualityCheckErrors->findByCompanyId($idCompany, $cQualityCheckErrors);
        
	return $cQualityCheckErrors;
    }
    
    /**
     * Findet alle aktuellen Fehler eines Typs für ein Unternehmen
     * 
     * @param string $idCompany
     * @param string $type
     * @return Marktjagd_Database_Enitity_QualityCheckErrors
     */
    public function findByCompanyIdAndType($idCompany, $type) {
        $eQualityCheckErrors = new Marktjagd_Database_Entity_QualityCheckErrors();
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();
        
        $mQualityCheckErrors->findByCompanyIdAndType($idCompany, $type, $eQualityCheckErrors);
        
        return $eQualityCheckErrors;
    }
    
    /**
     * Findet alle Fehler in einem bestimmten Zeitfenster
     * 
     * @param string $startTime
     * @param string $endTime
     * @return Marktjagd_Database_Collection_QualityCheckErrors
     */
    public function findLatestQualityCheckErrorsAdditions($startTime, $endTime) {
        $cQualityCheckErrors = new Marktjagd_Database_Collection_QualityCheckErrors();
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();
        
        $mQualityCheckErrors->findLatestQualityCheckErrorsAdditions($startTime, $endTime, $cQualityCheckErrors);
        
        return $cQualityCheckErrors;
    }

    /**
     * Ändert den Status eines Fehlers und fügt den bearbeitenden User hinzu
     * 
     * @param string $idQualityCheckErrors
     * @param string $status
     * @param string $user
     * @return boolean
     */
    public function changeStatus($idQualityCheckErrors, $status, $user) {
        $mQualityCheckErrors = new Marktjagd_Database_Mapper_QualityCheckErrors();

        if (!$mQualityCheckErrors->getDbTable()->changeStatus($idQualityCheckErrors, $status, $user)) {
            return false;
        }

        return true;
    }

}
