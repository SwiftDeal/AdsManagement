<?php

/**
 * Description of stat
 *
 * @author Faizan Ayubi
 */
class Stat extends Shared\Model {

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
     * @type integer
     * @index
     */
    protected $_link_id;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_item_id;
    
    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_click;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_amount;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_rpm;

    /**
     * @column
     * @readwrite
     * @type date
     */
    protected $_updated;

    public function is_bot() {
        $link = Link::first(array("id = ?" => $this->link_id), array("id", "short", "item_id", "user_id"));
        if (strpos($link->short, "goo.gl")) {
            $data = $link->stat();
            $googl = Framework\Registry::get("googl");
            $object = $googl->analyticsFull($link->short);
            
            if (isset($object)) {
                $referrers = $object->analytics->allTime->referrers;
                $countries = $object->analytics->allTime->countries;
                $browsers = $object->analytics->allTime->browsers;
                $platforms = $object->analytics->allTime->platforms;
                $clicks = $object->analytics->allTime->shortUrlClicks;

                $diff = abs($data["click"] - $clicks);

                if (((count($referrers) < 4) || (count($browsers) < 4) || (count($countries) < 4) || (count($platforms) < 4)) && ($diff > 400)) {
                    return true;
                }
            }
        }
        return false;
    }
    
}
