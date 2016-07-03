<?php

/**
 * @author Hemant Mann
 */
class BlockedUrl extends \Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label advertiser platform url
     */
    protected $_url;
}
