<?php

/**
 * @author Faizan Ayubi
 */
class Insight extends Shared\Model {

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
     * @type integer
     */
    protected $_click;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_amount;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_rpm;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_cpc;
}
