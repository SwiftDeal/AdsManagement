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
            $i[] = $ad->_id;
        }
        $impressions['cid'] = array('$in' => $i);
        $impressions['modified'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
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
        $clicks['created'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
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

    public function platforms() {
        $this->JSONview();$impressions = [];$clicks = [];$i = [];$total_impression = 0;$total_click = 0;
        $user_id = RequestMethods::get("user_id");$start = RequestMethods::get("start");$end = RequestMethods::get("end");
        $view = $this->getActionView();
        $adunits = \Models\Mongo\AdUnit::all(array("user_id" => $user_id));
        foreach ($adunits as $au) {
            $i[] = $au->_id;
        }
        $impressions['aduid'] = array('$in' => $i);
        $impressions['modified'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
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

        $clicks['aduid'] = array('$in' => $i);
        $clicks['created'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
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

    public function cunit() {
        $this->JSONview();$impressions = [];$clicks = [];$i = [];$total_impression = 0;$total_click = 0;
        $id = RequestMethods::get("id");$view = $this->getActionView();

        $adunit = \Models\Mongo\AdUnit::first(array("_id" => $id), array("_id", "created"));
        $start = RequestMethods::get("start", $adunit->created);$end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        
        $impressions['aduid'] = $adunit->_id;
        $impressions['modified'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
        
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

        $clicks['aduid'] = $adunit->_id;
        $clicks['created'] = array('$gte' => new MongoDate(strtotime($this->changeDate($start, -1))), '$lte' => new MongoDate(strtotime($this->changeDate($end, +1))));
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
}
