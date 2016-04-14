<?php

/**
 * Class to store post info
 * @author Hemant Mann
 */
class FBPost extends Shared\Model{
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @validate required, numeric
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
     * @validate required, numeric, min(3), max(50)
     * @label FBPage ID
     */
    protected $_fbpage_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     *
     * @validate required, min(5)
     * @label Facebook Post ID
     */
    protected $_fbpost_id;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     *
     * @validate required, min(5)
     * @label Short Url
     */
    protected $_link_id;    

}
