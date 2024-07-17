<?php

require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/New_Gen_Customer_Ci.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/New_Gen_Customer_Assets.php';

/*
 * This class represents the customer-specific configuration for layout of a NewGen brochure
 */

class New_Gen_Configuration
{
    public $customerCi;
    public $customerAssets;

    /**
     * New_Gen_Configuration constructor.
     * @param $customerCi
     */
    public function __construct($companyId)
    {
        $this->customerCi = new New_Gen_Customer_Ci($companyId);
        $this->customerAssets = new New_Gen_Customer_Assets($companyId);
    }
}
