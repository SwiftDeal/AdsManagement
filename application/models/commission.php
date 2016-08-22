<?php

/**
 * @author Faizan Ayubi
 */
class Commission extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @validate required
     */
    protected $_ad_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label description
     */
    protected $_description;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 3
     *
     * @label model - CPC, CPM etc
     * @validate required, alpha, min(3), max(3)
     */
    protected $_model;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @label payout for model
     * @validate required
     */
    protected $_revenue = null;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @label original Rate for model
     * @validate required
     */
    protected $_rate;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @validate required
     * @label Coverage
     */
    protected $_coverage;
}
