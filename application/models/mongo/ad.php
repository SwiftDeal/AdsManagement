<?php

/**
 * Ad Model
 * @author Hemant Mann
 */
namespace Models\Mongo;
class Ad extends \Shared\MongoModel {

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
    protected $_id;

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
     * @label image file name
     */
    protected $_image;


    /**
     * @column
     * @readwrite
     * @type array
     * @length 255
     *
     * @label video file name
     * @value Array of File Name
     */
    protected $_video = null;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(4)
     * @label text or video
     */
    protected $_type = "text";

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, max(255)
     * @label category
     * @value json_encode [Array]
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
     * @value json_encode [Array]
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
     * @type integer
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
    protected $_cpc = 200.00;

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
     * @length 50
     * @index
     *
     * @validate required
     */
    protected $_privacy = "private";
}
