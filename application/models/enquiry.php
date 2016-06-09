<?php

/**
 * @author Shreyansh Goel
 */
class Enquiry extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type text
     * 
     * @validate required
     */
    protected $_name;

    /**
    * @column
    * @readwrite
    * @type text
    *
    * @lable company website
    */
    protected $_c_website;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label phone
     */
    protected $_phone;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label email
     */
    protected $_email;

    /**
     * @column
     * @readwrite
     * @type integer
     *
     * @label contact = 1, request_demo = 2
     */
    protected $_type;

    /**
    * @column
    * @readwrite
    * @type text
    *
    * @label message
    */
    protected $_message;
}