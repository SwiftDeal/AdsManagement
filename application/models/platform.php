<?php

/**
 * Description of platform
 *
 * @author Faizan Ayubi
 */
class Platform extends Shared\Model{
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @validate required
     */
    protected $_user_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label name
     */
    protected $_type;

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
     *
     * @validate required, min(5)
     * @label url
     */
    protected $_url;
}
