<?php

/**
 * @author Hemant Mann
 */
use Shared\Utils as Utils;
class PageView extends Shared\Model {
	/**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @validate required, min(12)
     * @label Url
     */
    protected $_url;

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
     * @index
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @validate required, min(5)
     * @label Cookie
     */
    protected $_cookie;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_view = 0;
}