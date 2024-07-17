<?php

/*
 * This class represents a module in the layout of a NewGen brochure
 */

class New_Gen_Module
{
    public $name;
    public $version;
    public $capacity;
    public $highPriorityProducts;

    public function __construct(string $name, int $version, int $capacity, $highPriorityProducts) {
        $this->name = $name;
        $this->version = $version;
        $this->capacity = $capacity;

        // the $highPriorityProducts property is either null or an array
        if ($highPriorityProducts != null && gettype($highPriorityProducts) != 'array') {
            throw new Exception('Wrong type for NewGen Module initialization');
        }

        $this->highPriorityProducts = $highPriorityProducts;
    }
}
