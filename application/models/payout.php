<?php

/**
 * Central model to store payout info
 *
 * @author Faizan Ayubi
 */
class Payout extends \Shared\MongoModel {
    
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
     * @validate required, min(3), max(255)
     * @label type
     */
    protected $_type;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label account
     */
    protected $_account;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @label meta
     */
    protected $_meta;
}
