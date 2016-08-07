<?php

/**
 * @author Faizan Ayubi
 */
class Notification extends Shared\Model {

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
     * @type text
     */
    protected $_message;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label ad type
     * @value advertisers, publishers
     */
    protected $_target;
}
