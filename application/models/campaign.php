<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
class Campaign extends Shared\Model {

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
     * @type text
     * @length 3
     * @index
     *
     * @label model
     * @validate required, alpha, min(3), max(3)
     */
    protected $_model;
    
    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label url
     */
    protected $_url;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label target
     */
    protected $_target;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate max(255)
     * @label title
     */
    protected $_title;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(4)
     * @label image
     */
    protected $_image;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     *
     * @validate required
     * @label budget
     */
    protected $_budget;

    /**
     * @column
     * @readwrite
     * @type boolean
     *
     * @validate required
     * @label visibility of campaign
     */
    protected $_visibility = 0;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, max(255)
     * @label category
     */
    protected $_category;
    
    /**
     * @column
     * @readwrite
     * @type text
     *
     * @label description
     */
    protected $_description;
}
