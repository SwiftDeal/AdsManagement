<?php

/**
 * @author Faizan Ayubi
 */
class FBPage extends Shared\Model{
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @validate required
     * @index
     */
    protected $_user_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(3), max(32)
     * @label category
     */
    protected $_category;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 64
     * @index
     *
     * @validate required, min(5)
     * @label facebook pageid
     */
    protected $_fbid;

    /**
     * @column
     * @readwrite
     * @type integer
     * @length 20
     * @index
     *
     * @validate required, min(5)
     * @label facebook page likes
     */
    protected $_likes;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     *
     * @validate required, min(5)
     * @label facebook page website
     */
    protected $_website;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label facebook page name
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type text
     * 
     * @label FB token
     */
    protected $_token = '';
}
