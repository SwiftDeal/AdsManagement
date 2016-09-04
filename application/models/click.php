<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class Click extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_adid;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_ipaddr;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_referer;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_country;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_cookie;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @length 255
     * @index
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_ua;

     /**
     * @column
     * @readwrite
     * @type boolean
     * @index
     */
    protected $_is_bot = true;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_device;

    public static function checkFraud($clicks, $org = null) {
        $cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;

        if ($org) {
            $org_url = (is_null($org->url) || !$org->url) ? $org->domain.'.'.$cf->api->domain : $org->url;
        } else {
            $org_url = $cf->api->domain;
        }
        // The clicks here will be for a publisher on an AD
        $verified = [];
        foreach ($clicks as $c) {
            if (!$c->referer || preg_match('/'.$cf->api->domain.'/', $c->referer) || preg_match('#'.$org_url.'#', $c->referer)) {
                continue;
            }
            $key = $c->ipaddr;

            // Basic checking - For a single ip log single click
            // Advanced Checking - Check for referer + device
            if (array_key_exists($key, $verified)) {
                continue;
            } else {
                $verified[$key] = $c;
            }
        }
        return $verified;
    }

    public static function classify($clicks, $type = 'adid') {
        $classify = [];
        foreach ($clicks as $result) {
            $c = ArrayMethods::toObject($result);
            switch ($type) {
                case 'adid':
                case 'pid':
                    $key = Utils::getMongoID($c->$type);
                    break;
                
                default:
                    $key = Utils::getMongoID($c->adid);        
                    break;
            }
            if (!isset($classify[$key]) || !array_key_exists($key, $classify)) {
                $classify[$key] = [];
            }
            $classify[$key][] = $c;
        }
        return $classify;
    }
}
