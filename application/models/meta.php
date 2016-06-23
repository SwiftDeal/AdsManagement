<?php

/**
 * Description of meta
 *
 * @author Faizan Ayubi
 */
class Meta extends \Shared\MongoModel {
    
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
     * @label property
     */
    protected $_property;
    
    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label value
     */
    protected $_value;
}