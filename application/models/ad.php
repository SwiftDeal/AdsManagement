<?php

/**
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
     *
     * @label description
     */
    protected $_description;
    
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
     * @length 255
     * @index
     *
     * @validate required, max(255)
     * @label coverage of countries
     */
    protected $_coverage;

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
     * @type text
     * @length 255
     * @index
     *
     * @validate required, max(255)
     * @label frequency - daily or one time
     */
    protected $_frequency;

    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_start;

    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_end;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     *
     * @label cost per click
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type boolean
     *
     * @validate required
     * @label visibility of campaign
     */
    protected $_visibility = 0;
}
