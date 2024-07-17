<?php
class Marktjagd_Entity_PhpSpreadsheet_Worksheet
{
    /**
     * @var int
     */
    protected $_id;

    /**
     * @var string
     */
    protected $_title;

    /**
     * @var int
     */
    protected $_highestRow;

    /**
     * @var string
     */
    protected $_highestColumn;

    /**
     * @var int
     */
    protected $_highestColumnIndex;

    /**
     * @var array
     */
    protected $_data;

    /**
     * @param array $data
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setData($data)
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * @param string $highestColumn
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setHighestColumn($highestColumn)
    {
        $this->_highestColumn = $highestColumn;
        return $this;
    }

    /**
     * @return string
     */
    public function getHighestColumn()
    {
        return $this->_highestColumn;
    }

    /**
     * @param int $highestColumnIndex
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setHighestColumnIndex($highestColumnIndex)
    {
        $this->_highestColumnIndex = $highestColumnIndex;
        return $this;
    }

    /**
     * @return int
     */
    public function getHighestColumnIndex()
    {
        return $this->_highestColumnIndex;
    }

    /**
     * @param int $highestRow
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setHighestRow($highestRow)
    {
        $this->_highestRow = $highestRow;
        return $this;
    }

    /**
     * @return int
     */
    public function getHighestRow()
    {
        return $this->_highestRow;
    }

    /**
     * @param string $title
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setTitle($title)
    {
        $this->_title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @param int $id
     * @return Marktjagd_Entity_PhpExcel_Worksheet
     */
    public function setId($id)
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }
}