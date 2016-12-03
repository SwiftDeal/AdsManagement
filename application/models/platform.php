<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
class Platform extends Shared\Model {

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
     * @type text
     */
    protected $_type;

    public function setUrl($url) {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }

        if (!Utils::urlRegex($url)) {
            // throw new \Exception('Invalid URL');
        }
        $this->_url = $url;
    }

    public static function rssFeeds($org) {
        $users = \User::all(['org_id' => $org->_id, 'type' => 'advertiser'], ['_id']);
        $in = []; $result = [];
        foreach ($users as $u) {
            $in[] = $u->_id;
        }

        $platforms = \Platform::all([
            'user_id' => ['$in' => $in]
        ], ['_id', 'url', 'user_id', 'meta']);
        foreach ($platforms as $p) {
            if (isset($p->meta['rss'])) {
                $result[Utils::getMongoID($p->_id)] = $p;
            }
        }
        return $result;
    }
}
