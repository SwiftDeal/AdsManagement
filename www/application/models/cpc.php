<?php

/**
 * @author Faizan Ayubi
 */
class CPC extends Shared\Model {
    
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
    protected $_item_id;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 4,2
     * @label budget
     */
    protected $_budget = 0;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required
     * @label geotarget
     */
    protected $_geotarget = 0;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label medium (social, website, app)
     */
    protected $_medium;
}
