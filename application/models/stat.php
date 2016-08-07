<?php

/**
 * @author Hemant Mann
 */
class Stat extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @value Platform ID
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_impressions = 0;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type array
     */
    protected $_device = [];

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_revenue;
}
