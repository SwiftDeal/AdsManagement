<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
use \Curl\Curl;

class Analytics extends Manage {

    public function campaigns() {
        $this->JSONview();$impressions = [];$clicks = [];$i = [];$total_impression = 0;$total_click = 0;
        $user_id = RequestMethods::get("user_id");$start = RequestMethods::get("start");$end = RequestMethods::get("end");
        $view = $this->getActionView();
        $ads = \Models\Mongo\Ad::all(array("user_id" => $user_id));
        foreach ($ads as $ad) {
            $i[] = $ad->id;
        }
        
        $impressions['cid'] = array('$in' => $i);
        $impressions['modified'] = array('$gte' => new MongoDate(strtotime($start)), '$lte' => new MongoDate(strtotime($end)));
        $impr = Registry::get("MongoDB")->impressions;
        $icursor = $impr->find($impressions);
        foreach ($icursor as $id => $result) {
            $code = $result["country"];
            $total_impression += $result["hits"];
            if (array_key_exists($code, $ianalytics)) {
                $ianalytics[$code] += $result["hits"];
            } else {
                $ianalytics[$code] = $result["hits"];
            }
        }

        $clicks['cid'] = array('$in' => $i);
        $clicks['created'] = array('$gte' => new MongoDate(strtotime($start)), '$lte' => new MongoDate(strtotime($end)));
        $clk = Registry::get("MongoDB")->clicktracks;
        $cursor = $clk->find($clicks);
        foreach ($cursor as $id => $result) {
            $code = $result["country"];$total_click++;
            if (array_key_exists($code, $canalytics)) {
                $canalytics[$code] += 1;
            } else {
                $canalytics[$code] = 1;
            }
        }

        $view->set("clicks", $total_click);
        $view->set("impressions", $total_impression);
        $view->set("ianalytics", $ianalytics);
        $view->set("canalytics", $canalytics);
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
