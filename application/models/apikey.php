<?php

/**
 * Stores Api Key for access to users
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
class ApiKey extends Shared\Model {

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
    protected $_ips = [];

    /**
     * @column
     * @readwrite
     * @type text
     * @length 50
     */
    protected $_key;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_hits = 0;

    /**
     * @column
     * @readwrite
     * @type date
     */
    protected $_lastAccess = null;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @label Meta
     */
    protected $_meta = [];

    public function updateIps() {
        $request = RequestMethods::post('ips');
        $ips = ArrayMethods::clean($request);

        $this->_ips = $ips;
    }
}
