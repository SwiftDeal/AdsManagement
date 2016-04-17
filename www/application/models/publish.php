<?php

/**
 * Description of publish
 *
 * @author Faizan Ayubi
 */
class Publish extends Shared\Model {

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
    * @type decimal
    * @length 5,2
    */
    protected $_bouncerate;

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