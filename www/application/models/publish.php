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
    * @type text
    * @length 3
    */
    protected $_country;
}