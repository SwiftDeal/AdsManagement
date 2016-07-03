<?php

/**
 * Demo Model
 * @author Hemant Mann
 */
class Demo extends \Shared\Model {

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
