<?php

class Marktjagd_Database_Service_Task extends Marktjagd_Database_Service_Abstract
{

    /**
     * Funktion, um alle Aufgaben in der DB zu finden
     * 
     * @return Marktjagd_Database_Collection_Task
     */
    public function findAll()
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        $cTask = new Marktjagd_Database_Collection_Task();

        $mTask->fetchAll(null, $cTask);

        return $cTask;
    }
    
    /**
     * 
     * @param string $idTask
     * @return Marktjagd_Database_Entity_Task
     */
    public function find($idTask)
    {
        $eTask = new Marktjagd_Database_Entity_Task();
        $mTask = new Marktjagd_Database_Mapper_Task();
        $mTask->find($idTask, $eTask);
        
        return $eTask;
    }

    /**
     * Funktion, um alle Aufgaben eines Unternehmens anhand der Company-ID zu finden
     * 
     * @param int $idCompany
     * @return Marktjagd_Database_Collection_Task
     */
    public function findTasksByCompanyId($idCompany)
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        $cTask = new Marktjagd_Database_Collection_Task();

        $mTask->findTasksByCompanyId($idCompany, $cTask);

        return $cTask;
    }

    /**
     * Funktion, um alle zukünftigen Aufgaben eines Unternehmens anhand der Company-ID zu finden
     * 
     * @param int $idCompany
     * @return Marktjagd_Database_Collection_Task
     */
    public function findFutureTasksByCompanyId($idCompany, $startDate)
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        $cTask = new Marktjagd_Database_Collection_Task();

        $mTask->findFutureTasksByCompanyId($idCompany, $startDate, $cTask);

        return $cTask;
    }

    /**
     * Funktion, um Aufgabe aus der DB zu erhalten
     * 
     * @param string $taskId
     * @return Marktjagd_Database_Entity_Task $eTask
     */
    public function findSingleTask($taskId)
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        $cTask = new Marktjagd_Database_Collection_Task();

        $mTask->findSingleTaskByTaskId($taskId, $cTask);

        return $cTask;
    }

    /**
     * Funktion, um die zuletzt erstellte Aufgabe zu finden
     * 
     * @param array $aTask
     * @return Marktjagd_Database_Collection_Task $cTask
     */
    public function findLastCreatedTask($aTask)
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        $cTask = new Marktjagd_Database_Collection_Task();

        $mTask->findNewCreatedTask($aTask, $cTask);

        return $cTask;
    }

    /**
     * Funktion, um Task aus Relation anhand der Redmine-ID zu löschen
     * 
     * @param int $taskId
     * @return bool
     */
    public function deleteTask($taskId)
    {
        $mTask = new Marktjagd_Database_Mapper_Task();
        if (!$mTask->deleteTask($taskId)) {
            return false;
        }
        return true;
    }

}
