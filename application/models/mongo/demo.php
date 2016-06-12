<?php

/**
 * Ad Model
 * @author Hemant Mann
 */
namespace Models\Mongo;
class Demo extends \Shared\MongoModel {

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_url;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @length 255
     */
    protected $_ip;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @length 255
     */
    protected $_cookie;
}
