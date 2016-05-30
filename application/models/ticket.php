<?php

/**
 * @author Faizan Ayubi
 */
class Ticket extends \Shared\Model {
    
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
     * @length 255
     *
     * @label type
     */
    protected $_type = "other";

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(3), max(255)
     * @label subject
     */
    protected $_subject;
}
