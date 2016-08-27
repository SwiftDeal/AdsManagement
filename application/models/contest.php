<?php

/**
 * @author Faizan Ayubi
 */
class Contest extends Shared\Model {

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
     * @type decimal
     * @length 6,2
     *
     * @label revenue percent
     * @validate required
     */
    protected $_prize;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     */
    protected $_title;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
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

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];
}
