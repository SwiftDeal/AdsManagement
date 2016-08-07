<?php

/**
 * @author Faizan Ayubi
 */
class Platform extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     */
    protected $_url;

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];
}
