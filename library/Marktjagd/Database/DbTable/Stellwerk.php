<?php

class Marktjagd_Database_DbTable_Stellwerk extends Marktjagd_Database_DbTable_Abstract {
    
    protected $_name = 'Stellwerk';
    
    protected $_primary = 'idCompany';
    
    protected $_referenceMap = array (
        'IdCompany' => array(
         'columns'       => 'idCompany',
         'refTableClass' => 'Marktjagd_Database_DbTable_Company',
         'refColumns'    => 'idCompany')
    );
}