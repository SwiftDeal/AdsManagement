<?php

/**
 * @author Hemant Mann, Faizan Ayubi
 */
namespace Models\Mongo;
class AdsBlocked extends \Shared\MongoModel {

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
     *
     * @validate required, min(3)
     * @label advertiser platform url
     */
    protected $_url;
}
