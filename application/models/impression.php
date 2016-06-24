<?php

/**
 * Impression Model
 * @author Hemant Mann
 */
class Impression extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_aduid;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_cid;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate max(255)
     * @label Domain
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @label User Agent
     */
    protected $_ua;

    /**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @label Country
     */
    protected $_country;

    /**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @label Device
     */
    protected $_device;
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     *
     * @label Hits
     */
    protected $_hits;
}
