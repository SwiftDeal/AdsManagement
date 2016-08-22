<?php

/**
 * @author Faizan Ayubi
 */
class Conversion extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     *
     * @value click id
     */
    protected $_cid;

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
     * @length 255
     */
    protected $_pid;
}
