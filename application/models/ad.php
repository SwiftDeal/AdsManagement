<?php

/**
 * Description of item
 *
 * @author Faizan Ayubi
 */
class Ad extends Shared\Model {
    
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
     * @length 4,2
     */
    protected $_commission;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_targeturl;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label category
     */
    protected $_category;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 3
     *
     * @label model
     * @validate required, alpha, min(3), max(3)
     */
    protected $_model;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label model
     * @validate required, alpha, min(3), max(255)
     */
    protected $_geo;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_impressions;
    
    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks;
}
