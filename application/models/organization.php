<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use Shared\Utils as Utils;
class Organization extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label organizaion name
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label domain
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type array
     * 
     * @validate required
     * @label Tracking Domains
     */
    protected $_tdomains = [];

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label default url
     */
    protected $_url = null;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label default support email
     */
    protected $_email = null;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label network logo
     */
    protected $_logo = null;

    /**
     * @column
     * @readwrite
     * @type array
     * 
     * @validate required
     */
    protected $_billing = [];

    /**
     * @column
     * @readwrite
     * @type array
     * 
     * @validate required
     */
    protected $_meta = [];

    public function updateDomains() {
        $tdomains = $this->tdomains;
        $newDomains = RequestMethods::post('tdomains');

        $message = null;
        if (count($newDomains) != count($tdomains)) {
            $this->tdomains = $newDomains;
            $message = 'Please follow this guide to add tracking domain. <a href="http://vnative.com/add-custom-tracking-domain-ad-network/" target="_blank">Click here</a>';

            $this->save();
        } else {
            $newVal = array_diff($tdomains, $newDomains);
            if (count($newVal) > 0) {
                $this->tdomains = $newDomains;
                $message = 'Please follow this guide to add tracking domain. <a href="http://vnative.com/add-custom-tracking-domain-ad-network/" target="_blank">Click here</a>';
                $this->save();
            }
        }
        return $message;
    }

    public static function find(&$orgs, $key) {
        $key = Utils::getMongoID($key);
        if (!array_key_exists($key, $orgs)) {
            $org = self::first(['_id' => $key], ['url', 'meta']);
            $orgs[$key] = $org;
        } else {
            $org = $orgs[$key];
        }

        return $org;
    }

    public function users($type = "publisher") {
        $publishers = \User::all(["org_id = ?" => $this->_id, "type = ?" => $type], ["_id"]);

        return Utils::mongoObjectId(array_keys($publishers));
    }

    public function widgets($pubClicks = [], $adClicks = [], $pubs = []) {
        // No clicks found so no need for further processing
        if (count($pubClicks) === 0 && count($adClicks) === 0) {
            return false;
        }
        $result = ['publishers' => [], 'ads' => []];
        $meta = $this->meta; $meta["widget"] = [];
        
        // sort publishers based on clicks and find their details
        if (in_array("top10pubs", $meta["widgets"])) {
            arsort($pubClicks); array_splice($pubClicks, 10);
            foreach ($pubClicks as $pid => $count) {
                $u = $pubs[$pid];
                $result['publishers'][] = [
                    "_id" => $pid,
                    "name" => $u->name,
                    "count" => $count
                ];
            }
            $meta["widget"]["top10pubs"] = $result['publishers'];
        }

        if (in_array("top10ads", $meta["widgets"])) {
            arsort($adClicks); array_splice($adClicks, 10);
            foreach ($adClicks as $adid => $count) {
                $result['ads'][] = [
                    '_id' => $adid,
                    'clicks' => $count
                ];
            }
            $meta["widget"]["top10ads"] = $result['ads'];
        }

        $this->meta = $meta;
        $this->save();
    }
}
