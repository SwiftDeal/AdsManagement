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
     * @label type - facebook, website, app
     */
    protected $_type;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
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
