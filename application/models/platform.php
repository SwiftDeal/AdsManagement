<?php

/**
 * @author Faizan Ayubi
 */
class Platform extends Shared\Model {

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
     * @length 255
     * @index
     */
    protected $_url;

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];

    public function setUrl($url) {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }

        if (!Utils::urlRegex($url)) {
            throw new \Exception('Invalid URL');
        }
        $this->_url = $url;
    }
}
