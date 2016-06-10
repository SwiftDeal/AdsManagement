<?php

/**
 * @author Faizan Ayubi
 */
class Customer extends Shared\Model {

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
     * @type integer
     */
    protected $_staff_id;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 10
    */
    protected $_type;
}