<?php

class Marktjagd_Database_DbTable_CrawlerConfigCrawlerExecution extends Marktjagd_Database_DbTable_Abstract
{
    protected $_name = 'CrawlerConfig_x_CrawlerExecution';

    protected $_primary = 'idCrawlerConfigXCrawlerExecution';

    protected $_referenceMap = array(
      'CrawlerConfigCrawlerConfig' => array(
         'columns'       => 'CrawlerConfig_idCrawlerConfig',
         'refTableClass' => 'Marktjagd_Database_DbTable_CrawlerConfig',
         'refColumns'    => 'idCrawlerConfig'),
      'CrawlerExecutionCrawlerExecution' => array(
         'columns'       => 'CrawlerExecution_idCrawlerExecution',
         'refTableClass' => 'Marktjagd_Database_DbTable_CrawlerExecution',
         'refColumns'    => 'idCrawlerExecution'));
}