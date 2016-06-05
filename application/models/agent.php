<?php

/**
 * @author Faizan Ayubi
 */
class Agent extends \Shared\Model {
    
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
     * @type integer
     * @index
     * 
     * @validate required, numeric
     */
    protected $_user_id;

}
