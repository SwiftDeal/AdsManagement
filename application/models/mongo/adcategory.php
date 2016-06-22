<?php

/**
 * Ad Model
 * @author Hemant Mann
 */
namespace Models\Mongo;
class AdCategory extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_ad_id;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_category_id;

}
