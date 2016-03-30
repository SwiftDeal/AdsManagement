<?php

/**
 * @author Faizan Ayubi
 */
class SocialFB extends Shared\Model {
    
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
     * @length 64
     * @index
     */
    protected $_fbid;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_email;
}
