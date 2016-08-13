<?php

/**
 * @author Faizan Ayubi
 */
class Offer extends Shared\Model {

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
     * @type array
     */
    protected $_ad;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label ad type
     * @value advertisers, publishers
     */
    protected $_description;

    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_start;


    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_end;
}
