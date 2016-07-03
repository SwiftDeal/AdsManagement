<?php

/**
 * @author Faizan Ayubi
 */
class Subscription extends \Shared\Model {

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
    * @type date
    */
    protected $_start;

    /**
     * @column
     * @readwrite
     * @type date
     */
    protected $_end;

    /**
    * @column
    * @readwrite
    * @type integer
    * @length 5
    */
    protected $_period;
}