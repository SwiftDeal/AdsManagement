<?php

/**
 * Stores Api Key for access to users
 * @author Faizan Ayubi
 */
class PostBack extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_ad_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 50
     */
    protected $_event;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_data;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_type;
}
