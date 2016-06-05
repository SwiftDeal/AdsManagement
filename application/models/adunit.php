<?php

/**
 * @author Faizan Ayubi
 */
class AdUnit extends Shared\Model {
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

    public function stat($date = NULL) {
        $total_click = 0;$earning = 0;$analytics = array();$publishers = array();
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => 0);
        $doc = array("link_id" => (int) $this->id);
        if ($date) {
            $doc["created"] = $date;
        }

        $results = $this->mongodb($doc);
        if (is_array($results)) {
            $rpms = RPM::first(array("item_id = ?" => $this->item_id), array("value"));
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
                if (array_key_exists($result["user_id"], $publishers)) {
                    $publishers[$result["user_id"]] += $result["click"];
                } else {
                    $publishers[$result["user_id"]] = $result["click"];
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

    public function bitly() {
        if (strpos($this->short, "bit.ly")) {
            $conf = Framework\Registry::get("configuration")->parse("configuration/bitly");
            $b = new \Curl\Curl();
            $b->get('https://api-ssl.bitly.com/v3/link/referrers?access_token='.$conf->bitly->accesstoken.'&link='.urlencode($this->short));
            $b->close();

            return $b->response->data->referrers;
        }
        return false;
    }
}
