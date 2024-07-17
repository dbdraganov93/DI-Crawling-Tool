<?php

class Marktjagd_Database_Entity_AdvertisingSettings extends Marktjagd_Database_Entity_Abstract
{
    protected $_idAdvertisingSettings;
    protected $_idCompany;
    protected $_title;
    protected $_description;
    protected $_intervall;
    protected $_intervallType;
    protected $_startDate;
    protected $_endDate;
    protected $_adType;
    protected $_dateCreation;
    protected $_adStatus;
    protected $_nextDate;
    protected $_weekDays;
    protected $_ticketCheck;
    protected $_oCompany;
    
    protected $_aColumnMap = array(
        'idAdvertisingSettings' => 'IdAdvertisingSettings',
        'idCompany' => 'IdCompany',
        'title' => 'Title',
        'description' => 'Description',
        'intervall' => 'Intervall',
        'intervallType' => 'IntervallType',
        'startDate' => 'StartDate',
        'endDate' => 'EndDate',
        'adType' => 'AdType',
        'dateCreation' => 'DateCreation',
        'nextDate' => 'NextDate',
        'weekDays' => 'WeekDays',
        'ticketCheck' => 'TicketCheck',
        'adStatus' => 'AdStatus'
    );
    
    protected $_aRelationMap = array(
        'Company' => 'Marktjagd_Database_Entity_Company');
    
    protected $_aRelationPropertyMap = array(
        'Company' => 'Company');

    public function getIdAdvertisingSettings()
    {
        return $this->_idAdvertisingSettings;
    }

    public function getIdCompany()
    {
        return $this->_idCompany;
    }

    public function getTitle()
    {
        return $this->_title;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getIntervall()
    {
        return $this->_intervall;
    }

    public function getIntervallType()
    {
        return $this->_intervallType;
    }

    public function getStartDate()
    {
        return $this->_startDate;
    }

    public function getEndDate()
    {
        return $this->_endDate;
    }

    public function getAdType()
    {
        return $this->_adType;
    }

    public function getDateCreation()
    {
        return $this->_dateCreation;
    }

    public function getAdStatus()
    {
        return $this->_adStatus;
    }

    public function getNextDate()
    {
        return $this->_nextDate;
    }

    public function getWeekDays()
    {
        return $this->_weekDays;
    }

    public function getTicketCheck()
    {
        return $this->_ticketCheck;
    }

    public function getCompany()
    {
        return $this->_oCompany;
    }

    public function setIdAdvertisingSettings($idAdvertisingSettings)
    {
        $this->_idAdvertisingSettings = $idAdvertisingSettings;
        return $this;
    }

    public function setIdCompany($idCompany)
    {
        $this->_idCompany = $idCompany;
        return $this;
    }

    public function setTitle($title)
    {
        $this->_title = $title;
        return $this;
    }

    public function setDescription($description)
    {
        $this->_description = $description;
        return $this;
    }

    public function setIntervall($intervall)
    {
        $this->_intervall = $intervall;
        return $this;
    }

    public function setIntervallType($intervallType)
    {
        $this->_intervallType = $intervallType;
        return $this;
    }

    public function setStartDate($startDate)
    {
        $this->_startDate = $startDate;
        return $this;
    }

    public function setEndDate($endDate)
    {
        $this->_endDate = $endDate;
        return $this;
    }

    public function setAdType($adType)
    {
        $this->_adType = $adType;
        return $this;
    }

    public function setDateCreation($dateCreation)
    {
        $this->_dateCreation = $dateCreation;
        return $this;
    }

    public function setAdStatus($asStatus)
    {
        $this->_adStatus = $asStatus;
        return $this;
    }

    public function setNextDate($nextDate)
    {
        $this->_nextDate = $nextDate;
        return $this;
    }

    public function setWeekDays($weekDays)
    {
        $this->_weekDays = $weekDays;
        return $this;
    }

    public function setTicketCheck($ticketCheck)
    {
        $this->_ticketCheck = $ticketCheck;
        return $this;
    }

    public function setCompany(Marktjagd_Database_Entity_Company $oCompany)
    {
        $this->_oCompany = $oCompany;
        return $this;
    }

    /**
     * 
     * @return Marktjagd_Database_Mapper_AdvertisingSettings
     */
    public function getMapper()
    {
        return parent::getMapper();
    }

    /**
     * 
     * @param type $bNull
     * @param type $bForceInsert
     */
    public function save($bNull = false, $bForceInsert = false)
    {
        $this->getMapper()->save($this, $bNull, $bForceInsert);
    }

    /**
     * 
     * @param type $mId
     * @return type
     */
    public function find($mId)
    {
        return $this->getMapper()->find($mId, $this);
    }

}
