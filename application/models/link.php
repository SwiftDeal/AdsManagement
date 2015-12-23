<?php

/**
 * Description of link
 *
 * @author Faizan Ayubi
 */
use ClusterPoint\DB as DB;
class Link extends Shared\Model {
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     */
    protected $_short;
    
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
     * @index
     */
    protected $_user_id;

    public function googl() {
        $googl = Framework\Registry::get("googl");
        $object = $googl->analyticsFull($this->short);
        return isset($object) ? $object : NULL;
    }

    public function clusterpoint() {
        $count = 0;
        $clusterpoint = new DB();
        $query = "SELECT * FROM stats WHERE item_id == '{$this->item_id}' && user_id == '{$this->user_id}' LIMIT 0, 100";
        $results = $clusterpoint->index($query);
        if ($results) {
            foreach ($results as $result) {
                $count += $result->click;
            }
        }
        return $count;
    }

    public function stat($duration = "allTime") {
        $domain_click = 0;
        $country_click = 0;
        $earning = 0;
        $verified = 0;
        $code = "";
        $country_code = array("IN", "US", "CA", "AU","GB");
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "verified" => 0);

        $verified = $this->clusterpoint();
        
        $stat = $this->googl($this->short);
        if($stat) {
            $googl = $stat->analytics->$duration;
            $total_click = $googl->shortUrlClicks;

            if ($total_click) {
                $referrers = $googl->referrers;
                foreach ($referrers as $referer) {
                    if ($referer->id == 'chocoghar.com') {
                        $domain_click = $referer->count;
                    }
                }
                $total_click -= $domain_click;

                //commision
                $meta = Meta::first(array("property = ?" => "commision"), array("value"));
                $commision = 1 - ($meta->value)/100;

                $countries = isset($googl->countries) ? $googl->countries : NULL;
                $rpms = RPM::first(array("item_id = ?" => $this->item_id), array("value"));
                $rpm = json_decode($rpms->value);
                if ($countries) {
                    foreach ($countries as $country) {
                        if (in_array($country->id, $country_code)) {
                            $code = $country->id;
                            $earning += ($rpm->$code)*($country->count)*($commision)/1000;
                            $country_click += $country->count;
                        }
                    }
                }

                if($total_click > $country_click) {
                    $earning += ($rpm->NONE)*($total_click - $country_click)*($commision)/1000;
                }

                $return = array(
                    "click" => round($total_click*$commision),
                    "rpm" => round(($earning*1000)/($total_click), 2),
                    "earning" => round($earning, 2),
                    "verified" => $verified
                );
            }
        }
        return $return;
    }
}
