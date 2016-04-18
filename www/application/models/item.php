<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
class Item extends Shared\Model {

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
     * @type text
     * @length 3
     * @index
     *
     * @label model
     * @validate required, alpha, min(3), max(3)
     */
    protected $_model;
    
    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required, min(3)
     * @label target url
     */
    protected $_url;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, min(3), max(255)
     * @label title
     */
    protected $_title;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(4)
     * @label image
     */
    protected $_image;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     *
     * @validate required, min(4)
     * @label budget
     */
    protected $_budget;

    /**
     * @column
     * @readwrite
     * @type boolean
     *
     * @validate required
     * @label visibility of campaign
     */
    protected $_visibility;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     *
     * @validate required, min(3)
     * @label category
     */
    protected $_category;
    
    /**
     * @column
     * @readwrite
     * @type text
     *
     * @label description
     */
    protected $_description;

    public function stats($date = NULL) {
        $collection = \Framework\Registry::get("MongoDB")->clicks;
        $total_click = 0;$earning = 0;$analytics = array();$publishers = array();
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => 0);
        $doc = array("item_id" => $this->id);
        if ($date) {
            $doc["created"] = $date;
        }
        $records = $collection->find($doc);
        if (isset($records)) {
            //rpm
            $rpms = RPM::first(array("item_id = ?" => $this->id), array("value"));
            $rpm = json_decode($rpms->value, true);

            foreach ($records as $record) {
                $u = null;
                $u = User::first(array("id = ?" => $record["user_id"], "live = ?" => true), array("id"));
                if ($u) {
                    $code = $record["country"];
                    $total_click += $record["click"];
                    if (array_key_exists($code, $rpm)) {
                        $earning += ($rpm[$code])*($record["click"])/1000;
                    } else {
                        $earning += ($rpm["NONE"])*($record["click"])/1000;
                    }
                    if (array_key_exists($code, $analytics)) {
                        $analytics[$code] += $record["click"];
                    } else {
                        $analytics[$code] = $record["click"];
                    }
                    if (array_key_exists($record["user_id"], $publishers)) {
                        $publishers[$record["user_id"]] += $record["click"];
                    } else {
                        $publishers[$record["user_id"]] = $record["click"];
                    }
                }
            }

            if ($total_click > 0) {
                $return = array(
                    "click" => round($total_click),
                    "rpm" => round($earning*1000/$total_click, 2),
                    "earning" => round($earning, 2),
                    "analytics" => $analytics,
                    "publishers" => $publishers
                );
            }
        }
        return $return;
    }
}
