<?php

/**
 * @author Faizan Ayubi
 */
class Conversation extends \Shared\MongoModel {
    
    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     *
     * @validate required
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     *
     * @validate required
     */
    protected $_ticket_id;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label message
     */
    protected $_message;
}
