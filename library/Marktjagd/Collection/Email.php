<?php

class Marktjagd_Collection_Email
{
    protected $_elements = array();
    
    /**
     * Funktion, um einzelne E-Mail-Entität der Collection hinzuzufügen
     * 
     * @param Marktjagd_Entity_Email $element

     */
    public function addElement($element) {
        $this->_elements[$element->getHash()] = $element;
        return true;
    }
    
    /**
     * Funktion um Array von E-Mail-Entitäten der Collection hinzuzufügen
     * 
     * @param array $elements
     * @return bool
     */
    public function addElements($elements) {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
        return true;
    }
    
    /**
     * Funktion, um E-Mail-Collection zu leeren
     */
    public function clearElements() {
        $this->_elements = array();
        return true;
    }
    
    /**
     * Funktion, um alle E-Mail-Enitäten der Collection zu erhalten
     * 
     * @return Marktjagd_Entity_Email[]
     */
    public function getElements() {
        return $this->_elements;
    }
    
}