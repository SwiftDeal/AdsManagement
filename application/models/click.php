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
    protected $_browser;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_os;

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

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_p1 = null;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_p2 = null;

    public static function hourly() {
        self::deleteAll(['is_bot' => true]);
    }

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
            $key = Utils::getMongoID($c->$type ?? '');

            if (strlen($key) == 0) {
                $key = "Empty";
            }
            $key = str_replace(".", "-", $key);

            if (!isset($classify[$key]) || !array_key_exists($key, $classify)) {
                $classify[$key] = [];
            }
            $classify[$key][] = $c;
        }
        return $classify;
    }

    public static function counter($clicks) {
        $result = [];
        foreach ($clicks as $k => $v) {
            $result[$k] = count($v);
        }
        return $result;
    }

    public static function classifyInfo($opts = []) {
        $clicks = $opts['clicks']; $type = $opts['type'];
        $arr = $opts['arr'];

        $deviceClicks = self::classify($clicks, $type);
        $from = self::counter($deviceClicks);
        ArrayMethods::add($from, $arr);

        return $arr;
    }
}
