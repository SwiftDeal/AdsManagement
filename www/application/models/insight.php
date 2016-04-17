<?php

/**
 * @author Faizan Ayubi
 */
class Insight extends Shared\Model {

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

    public function stat($date = NULL) {
        $total_click = 0;$earning = 0;$analytics = array();
        $return = array("click" => 0, "cpc" => 0, "earning" => 0, "analytics" => 0);

        $results = array();
        if (is_array($results)) {
            $cpcs = CPC::first(array("item_id = ?" => $this->item_id), array("value"));
            $cpc = json_decode($cpcs->value, true);

            foreach ($results as $result) {
                $code = $result["country"];
                $total_click += $result["count"];
                if (array_key_exists($code, $cpc)) {
                    $earning += ($cpc[$code])*($result["count"])/1000;
                } else {
                    $earning += ($cpc["NONE"])*($result["count"])/1000;
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
                    "cpc" => round($earning*1000/$total_click, 2),
                    "earning" => round($earning, 2),
                    "analytics" => $analytics
                );
            }
        }
        
        return $return;
    }
}
