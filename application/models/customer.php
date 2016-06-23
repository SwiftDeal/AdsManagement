<?php

/**
 * @author Faizan Ayubi
 */
class Customer extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @validate required
     */
    protected $_user_id;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 5
    */
    protected $_country;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     *
     * @validate required
     * @label balance
     */
    protected $_balance;

    /**
     * @column
     * @readwrite
     * @type mongoid
     */
    protected $_staff_id = null;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 10
    */
    protected $_type;
}