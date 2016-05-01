<?php

/**
 * @author Faizan Ayubi
 */
class GAInsight extends Shared\Model {

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
     * @index
     */
    protected $_advert_id;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_website_id;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks;
    
    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_sessions;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_pageviews;

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
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_bouncerate;
}
