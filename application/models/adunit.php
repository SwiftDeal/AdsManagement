<?php

/**
 * AdUnit Mongo Model
 * @author Faizan Ayubi, Hemant Mann
 */
namespace Models\Mongo;
class AdUnit extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     *
     * @validate required
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required
     */
    protected $_category;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type array
     * @length 255
     * @index
     *
     * @validate required
     */
    protected $_type = [];

    /**
     * @column
     * @readwrite
     * @type text
     * @length 50
     * @index
     *
     * @validate required
     * @value "global" | "local"
     */
    protected $_privacy = "global";
}
