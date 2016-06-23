<?php

/**
 * AdCategory Model
 * @author Hemant Mann
 */
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
