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
     * @type decimal
     * @length 4,2
     *
     * @validate required
     * @label commission
     */
    protected $_commission;

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
        $total_click = 0;$earning = 0;$analytics = array();
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => 0);
        $doc = array("item_id" => $this->id);
        if ($date) {
            $doc["created"] = $date;
        }
        $results = $this->mongodb($doc);
        if (is_array($results)) {
            //rpm
            $rpms = RPM::first(array("item_id = ?" => $this->id), array("value"));
            $rpm = json_decode($rpms->value, true);

            foreach ($results as $result) {
                $code = $result["country"];
                $total_click += $result["count"];
                if (array_key_exists($code, $rpm)) {
                    $earning += ($rpm[$code])*($result["count"])/1000;
                } else {
                    $earning += ($rpm["NONE"])*($result["count"])/1000;
                }

                if (array_key_exists($code, $analytics)) {
                    $analytics[$code] += $result["count"];
                } else {
                    $analytics[$code] = $result["count"];
                }
            }

            if ($total_click > 0) {
                $return = array(
                    "click" => round($total_click),
                    "rpm" => round($earning*1000/$total_click, 2),
                    "earning" => round($earning, 2),
                    "analytics" => $analytics
                );
            }
        }
        return $return;
    }
}
