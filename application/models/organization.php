<?php

/**
 * @author Faizan Ayubi
 */
class Organization extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     * @validate required
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label company
     */
    protected $_company;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label address
     */
    protected $_address;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label city
     */
    protected $_city;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label state
     */
    protected $_state;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label zipcode
     */
    protected $_zipcode;
}