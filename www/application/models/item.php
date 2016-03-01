<?php

/**
 * Description of item
 *
 * @author Faizan Ayubi
 */
class Item extends Shared\Model {

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
     * @label target url
     */
    protected $_url;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, min(3), max(255)
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
     * @length 4,2
     *
     * @validate required
     * @label commission
     */
    protected $_commission;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, min(3)
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
