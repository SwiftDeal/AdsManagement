<?php

/**
 * @author Faizan Ayubi
 */
class Impression extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_adid;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_ua;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_device;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_country;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_hits;
}
