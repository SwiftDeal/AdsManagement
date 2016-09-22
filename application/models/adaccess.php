<?php

/**
 * @author Faizan Ayubi
 */
class Adaccess extends Shared\Model {

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
    protected $_ad_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;
}
