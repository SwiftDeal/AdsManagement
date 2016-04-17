<?php

/**
 * @author Faizan Ayubi
 */
class Advert extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     * @validate required
     */
    protected $_user_id;

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

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label cpc value
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @label GA token
     */
    protected $_gatoken;
}