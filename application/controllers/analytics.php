<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
use \Curl\Curl;

class Analytics extends Manage {

    /**
     * Today Stats of user
     * @return array earnings, clicks, rpm, analytics
     * @before _secure
     */
    public function stats($created = NULL, $auth = 1, $user_id = NULL, $item_id = NULL) {
        $this->seo(array("title" => "Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $total_click = 0;$earning = 0;$analytics = array();$query = array();$publishers = array();
        $rpm = array("IN" => 135, "US" => 220, "CA" => 220, "AU" => 220, "GB" => 220, "NONE" => 80);
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => array());

        is_null($created) ? NULL : $query['created'] = $created;
        is_null($item_id) ? NULL : $query['item_id'] = $item_id;
        if ($auth) {
            $query['user_id'] = (is_null($user_id) ? $this->user->id : $user_id);
        }

        $collection = Registry::get("MongoDB")->clicks;

        $cursor = $collection->find($query);
        foreach ($cursor as $id => $result) {
            $code = $result["country"];
            $total_click += $result["click"];
            if (array_key_exists($code, $rpm)) {
                $earning += ($rpm[$code])*($result["click"])/1000;
            } else {
                $earning += ($rpm["NONE"])*($result["click"])/1000;
            }
            if (array_key_exists($code, $analytics)) {
                $analytics[$code] += $result["click"];
            } else {
                $analytics[$code] = $result["click"];
            }
            if (array_key_exists($result["user_id"], $publishers)) {
                $publishers[$result["user_id"]] += $result["click"];
            } else {
                $publishers[$result["user_id"]] = $result["click"];
            }
        }
        $publishers = $this->array_sort($publishers, 'click', SORT_DESC);$rank = array();
        foreach ($publishers as $key => $value) {
            array_push($rank, array(
                "user_id" => $key,
                "clicks" => $value
            ));
        }
        arsort($analytics);
        arsort($publishers);

        if ($total_click > 0) {
            $return = array(
                "click" => round($total_click),
                "rpm" => $this->user->convert(round($earning*1000/$total_click, 2)),
                "earning" => $this->user->convert(round($earning, 2)),
                "analytics" => $analytics,
                "publishers" => $rank
            );
        }

        $view->set("stats", $return);
    }

    protected function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') == $date;
    }


    /**
     * Analytics of Single Campaign Datewise
     * @return array earnings, clicks, cpc, analytics
     * @before _secure
     */
    public function ad($id, $created = NULL) {
        $this->seo(array("title" => "Ad Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $total_click = 0;$spent = 0;$analytics = array();$query = array();$i = array();
        $return = array("click" => 0, "spent" => 0, "analytics" => array());
        
        $ad = Ad::first(["id = ?" => $id], ["cpc"]);
        if ($this->validateDate($created)) {
            $query['created'] = $created;
        }
        $query["cid"] = (int) $id;
        
        $collection = Registry::get("MongoDB")->clicktracks;
        $cursor = $collection->find($query);
        foreach ($cursor as $result) {
            $code = $result["country"];
            $total_click++;
            if (array_key_exists($code, $analytics)) {
                $analytics[$code] += $result["click"];
            } else {
                $analytics[$code] = $result["click"];
            }
        }

        if ($total_click > 0) {
            $return = array(
                "click" => round($total_click),
                "spent" => $this->user->convert(round($spent, 2)),
                "analytics" => $analytics
            );
        }

        $view->set("stats", $return);
        $view->set("query", $query);
        $view->set("cpc", $cpc);
    }
}
