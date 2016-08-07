<?php

/**
 * @author Faizan Ayubi
 */
class Link extends Shared\Model {

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
     * @type mongoid
     * @index
     */
    protected $_ad_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     * @value Subdomain of the APP
     */
    protected $_app = '';

    public function getUrl() {
        return 'http://' . $this->domain . '/'. $this->getMongoID();
    }

    public function clicks() {
        $count = 0;
        $clicks = Click::all([
            'adid' => $this->ad_id,
            'pid' => $this->user_id
        ], ['ipaddr', 'referer', 'ua']);
        foreach ($clicks as $c) {
            if (!$c->fraud()) {
                $count++;
            }
        }
        return $count;
    }
}
