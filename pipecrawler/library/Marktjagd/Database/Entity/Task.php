<?php

class Marktjagd_Database_Entity_Task extends Marktjagd_Database_Entity_Abstract {

    protected $_idTask;
    protected $_idCompany;
    protected $_dateCreation;
    protected $_title;
    protected $_description;
    protected $_intervall;
    protected $_intervallType;
    protected $_startDate;
    protected $_nextDate;
    protected $_taskStatus;
    protected $_assignedTo;

    /**
     * Contains mapping of table columns to function
     *
     * @var array
     */
    protected $_aColumnMap = array(
        'idTask' => 'IdTask',
        'idCompany' => 'IdCompany',
        'dateCreation' => 'DateCreation',
        'title' => 'Title',
        'description' => 'Description',
        'intervall' => 'Intervall',
        'intervallType' => 'IntervallType',
        'startDate' => 'StartDate',
        'taskStatus' => 'TaskStatus',
        'nextDate' => 'NextDate',
        'assignedTo' => 'AssignedTo'
    );

    /**
     * 
     * @return string
     */
    public function getStartDate() {
        return $this->_startDate;
    }

    /**
     * 
     * @return string
     */
    public function getNextDate() {
        return $this->_nextDate;
    }

    /**
     * 
     * @return string
     */
    public function getTaskStatus() {
        return $this->_taskStatus;
    }

    /**
     * 
     * @return string
     */
    public function getIdTask() {
        return $this->_idTask;
    }

    /**
     * 
     * @return string
     */
    public function getIdCompany() {
        return $this->_idCompany;
    }

    /**
     * 
     * @return string
     */
    public function getDateCreation() {
        return $this->_dateCreation;
    }

    /**
     * 
     * @return string
     */
    public function getTitle() {
        return $this->_title;
    }

    /**
     * 
     * @return string
     */
    public function getDescription() {
        return $this->_description;
    }

    /**
     * 
     * @return string
     */
    public function getIntervall() {
        return $this->_intervall;
    }

    /**
     * 
     * @return string
     */
    public function getIntervallType() {
        return $this->_intervallType;
    }

    /**
     * 
     * @return string
     */
    public function getAssignedTo() {
        return $this->_assignedTo;
    }

    /**
     * 
     * @param string $idTask
     * @return Marktjagd_Database_Entity_Task
     */
    public function setIdTask($idTask) {
        $this->_idTask = $idTask;
        return $this;
    }

    /**
     * 
     * @param string $idCompany
     * @return Marktjagd_Database_Entity_Task
     */
    public function setIdCompany($idCompany) {
        $this->_idCompany = $idCompany;
        return $this;
    }

    /**
     * 
     * @param string $dateCreation
     * @return Marktjagd_Database_Entity_Task
     */
    public function setDateCreation($dateCreation) {
        $this->_dateCreation = $dateCreation;
        return $this;
    }

    /**
     * 
     * @param string $title
     * @return Marktjagd_Database_Entity_Task
     */
    public function setTitle($title) {
        $this->_title = $title;
        return $this;
    }

    /**
     * 
     * @param string $description
     * @return Marktjagd_Database_Entity_Task
     */
    public function setDescription($description) {
        $this->_description = $description;
        return $this;
    }

    /**
     * 
     * @param string $intervall
     * @return Marktjagd_Database_Entity_Task
     */
    public function setIntervall($intervall) {
        $this->_intervall = $intervall;
        return $this;
    }

    /**
     * 
     * @param string $intervallType
     * @return Marktjagd_Database_Entity_Task
     */
    public function setIntervallType($intervallType) {
        $this->_intervallType = $intervallType;
        return $this;
    }

    /**
     * 
     * @param string $startDate
     * @return Marktjagd_Database_Entity_Task
     */
    public function setStartDate($startDate) {
        $this->_startDate = $startDate;
        return $this;
    }

    /**
     * 
     * @param string $nextDate
     * @return Marktjagd_Database_Entity_Task
     */
    public function setNextDate($nextDate) {
        $this->_nextDate = $nextDate;
        return $this;
    }

    /**
     * 
     * @param string $status
     * @return Marktjagd_Database_Entity_Task
     */
    public function setTaskStatus($status) {
        $this->_taskStatus = $status;
        return $this;
    }

    /**
     * 
     * @param string $assignedTo
     * @return Marktjagd_Database_Entity_Task
     */
    public function setAssignedTo($assignedTo) {
        $this->_assignedTo = $assignedTo;
        return $this;
    }

    /**
     * Returns the mapper class, if no one exists, default will be created.
     *
     * @return  Marktjagd_Database_Mapper_Task
     */
    public function getMapper() {
        return parent::getMapper();
    }

    /**
     * Saves data to database If the primary key is set,
     * data will be updated.
     *
     * @param bool $bNull Save also null values
     *
     * @return void
     */
    public function save($bNull = false) {
        $this->getMapper()->save($this, $bNull);
    }

    /**
     * Loads the data by primary key(s). By multiple primary
     * keys use an array with the values of the primary key columns.
     *
     * @param mixed $mId Primary key(s) value(s)
     *
     * @return bool True if found, otherwise false
     */
    public function find($mId) {
        return $this->getMapper()->find($mId, $this);
    }

}
