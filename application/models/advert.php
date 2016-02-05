<?php

/**
 * Description of advert
 *
 * @author Faizan Ayubi
 */
class Advert extends Shared\Model {

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
     * @length 4
     */
    protected $_location;
    
    /**
    * @column
    * @readwrite
    * @type text
    * @length 32
    */
    protected $_account;
}