<?php

/**
 * @author Faizan Ayubi
 */
class Staff extends \Shared\MongoModel {
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     *
     * @validate required
     * @label Property
     */
    protected $_skype;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * 
     * @validate required, numeric
     */
    protected $_user_id;

}