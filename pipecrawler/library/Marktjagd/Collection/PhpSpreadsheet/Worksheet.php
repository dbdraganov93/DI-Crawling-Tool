<?php
class Marktjagd_Collection_PhpSpreadsheet_Worksheet
{
    /**
     * @var array
     */
    protected $_elements = array();

    /**
     * @param Marktjagd_Collection_PhpSpreadsheet_Worksheet $element
     */
    public function addElement($element)
    {
        $this->_elements[$element->getId()] = $element;
    }

    /**
     * @param array $elements
     */
    public function addElements($elements)
    {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
    }

    /**
     * @return array
     */
    public function getElements()
    {
        return $this->_elements;
    }

    /**
     * @param int $id
     * @return Marktjagd_Entity_PhpSpreadsheet_Worksheet
     */
    public function getElement($id)
    {
        if (array_key_exists($id, $this->_elements)) {
            return $this->_elements[$id];
        } else {
            return false;
        }
    }
}