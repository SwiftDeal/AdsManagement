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
     * @before _secure
     */
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

    /**
     * @before _secure
     */
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

    /**
     * @before _secure
     */
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

    /**
     * @before _secure, changeLayout, _admin
     */
    public function impressions() {
        $this->seo(array("title" => "Impressions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();$impressions = [];$total_impression = 0;
        
        if (RequestMethods::get("action") == "readImpr") {
            $start = RequestMethods::get("start");$end = RequestMethods::get("end");
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

            $view->set("impressions", $total_impression);
            $view->set("ianalytics", $ianalytics);
        }
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function clicks() {
        $this->seo(array("title" => "Clicks", "view" => $this->getLayoutView()));
        $view = $this->getActionView();$impressions = [];$total_impression = 0;
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-1 day')));$end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        
        if (RequestMethods::get("action") == "readClk") {
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
            $view->set("canalytics", $canalytics);
        }

        $view->set("start", $start);
        $view->set("end", $end);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function demos() {
        $this->seo(array("title" => "Platform Demos", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $where = array("user_id" => $this->user->id);
        
        $ads = \Models\Mongo\Demo::all($where, array("*"), "created", -1, $limit, $page);
        $count = \Models\Mongo\Demo::count($where);

        $view->set("ads", $ads);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }
}
