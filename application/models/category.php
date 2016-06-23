<?php

/**
 * Category Model
 * @author Hemant Mann
 */
class Category extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_id;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label url
     */
    protected $_name;

}
