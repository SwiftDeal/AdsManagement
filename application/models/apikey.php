<?php

/**
 * @author Faizan Ayubi
 */
class Apikey extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @label whitelisted IPs
     * @value array of ips
     */
    protected $_ips;

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];
}
