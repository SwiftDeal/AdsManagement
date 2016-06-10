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
     * @type integer
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
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
     * @type text
     * @length 255
     * @index
     */
    protected $_type;
}
