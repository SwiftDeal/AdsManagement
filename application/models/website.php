<?php

/**
 * @author Hemant Mann
 */
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class Website extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(3), max(100)
     * @label Property Name
     */
    protected $_name;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 64
     * @index
     *
     * @validate required, min(5)
     * @label Google Analytics ID
     */
    protected $_gaid;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label url
     */
    protected $_url;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_advert_id;

    public function campaign() {
        $collection = Registry::get("MongoDB")->ga_stats;
        
        $records = $collection->find(array("website_id" => (int) $this->id), ['sessions']);
        if (!isset($records)) {
            return 0;
        }

        $sessions = 0;
        foreach ($records as $r) {
            $r = ArrayMethods::toObject($r);
            $sessions += (int) $r->sessions;
        }
        return $sessions;
    }

    public function analytics() {
        $total_click = 0;$spent = 0;$analytics = array();$query = array();$i = array();
        $return = array("click" => 0, "cpc" => 0, "spent" => 0, "analytics" => array());

        $advert = Advert::first(array("id = ?" => $this->advert_id), array("cpc", "user_id"));
        $cpc = json_decode($advert->cpc, true);
        
        $items = Item::all(array("user_id = ?" => $advert->user_id), array("id"));
        foreach ($items as $item) {
            $i[] = $item->id;
        }
        $query['item_id'] = array('$in' => $i);
        $collection = Registry::get("MongoDB")->clicks;
        $cursor = $collection->find($query);
        foreach ($cursor as $result) {
            $u = User::first(array("id = ?" => $result["user_id"], "live = ?" => true), array("id"));
            if ($u) {
                $code = $result["country"];
                $total_click += $result["click"];
                if (array_key_exists($code, $cpc)) {
                    $spent += ($cpc[$code])*($result["click"])/1000;
                } else {
                    $spent += ($cpc["NONE"])*($result["click"])/1000;
                }
                if (array_key_exists($code, $analytics)) {
                    $analytics[$code] += $result["click"];
                } else {
                    $analytics[$code] = $result["click"];
                }
            }
        }

        if ($total_click > 0) {
            $return = array(
                "click" => round($total_click),
                "cpc" => round($spent*1000/$total_click, 2),
                "spent" => round($spent, 2),
                "analytics" => $analytics
            );
        }

        return $return;
    }
}
