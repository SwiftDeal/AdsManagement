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

    public function getUrl($url = null) {
        if ($url) {
            $link = 'http://' . $url . '/'. $this->getMongoID();
        } else {
            $link = 'http://' . $this->domain . '/'. $this->getMongoID();
        }
        return $link;
    }

}
