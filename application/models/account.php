<?php

/**
 * Description of bank
 *
 * @author Faizan Ayubi
 */
class Account extends \Shared\Model {
    
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
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label tax pan
     */
    protected $_pan;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     * @label balance
     */
    protected $_balance;
}
