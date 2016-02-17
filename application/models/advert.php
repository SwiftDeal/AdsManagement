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
     * 
     * @validate required, alpha, min(2), max(4)
     * @label location
     */
    protected $_location;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 32
    *
    * @validate required, alpha, min(3), max(32)
    * @label account
    */
    protected $_account = "basic";
}