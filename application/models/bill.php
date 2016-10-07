<?php

/**
 * @author Faizan Ayubi
 */
class Bill extends Shared\Model {

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
     * @type integer
     */
    protected $_impressions;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @validate required
     * @label impression million cost
     */
    protected $_mic;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @validate required
     * @label click thousand cost
     */
    protected $_tcc;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @validate required
     * @label amount charged
     */
    protected $_amount;
}
