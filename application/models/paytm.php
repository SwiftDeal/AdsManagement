<?php

/**
 * @author Faizan Ayubi
 */
class Paytm extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_user_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 32
     * 
     * @validate required, min(10), max(15)
     * @label paytm number
     */
    protected $_phone;
}
