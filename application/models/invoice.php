<?php

/**
 * @author Faizan Ayubi
 */
class Invoice extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required
     * @label user type
     * @value publisher or advertiser or admin
     */
    protected $_utype;

    /**
     * @column
     * @readwrite
     * @type date
     *
     * @validate required
     * @label invoice start date
     */
    protected $_start;

    /**
     * @column
     * @readwrite
     * @type date
     *
     * @validate required
     * @label invoice end date
     */
    protected $_end;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required
     * @label payment type
     * @value wire, paypal, paytm etc
     */
    protected $_ptype = null;

    /**
     * @column
     * @readwrite
     * @type integer
     *
     * @validate required
     * @label period for invoice
     */
    protected $_period;

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
    * @type array
    */
    protected $_meta = [];
}
