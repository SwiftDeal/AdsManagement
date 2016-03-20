<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use \Curl\Curl;

class Analytics extends Manage {
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function googl() {
        //$this->JSONview();
        $this->noview();
        $view = $this->getActionView();
        
        if (RequestMethods::get("link")) {
            $link_id = RequestMethods::get("link");
            $link = Link::first(array("id = ?" => $link_id), array("id", "short", "item_id", "user_id"));
            if ($link->googl()) {
                $googl = Registry::get("googl");
                $object = $googl->analyticsFull($link->short);
                $view->set("googl", $object);
                echo "<pre>", print_r($object), "</pre>";
            }
            $view->set("link", $link);
        }
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function content($id='') {
        $this->seo(array("title" => "Content Analytics", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $item = Item::first(array("id = ?" => $id));

        $earn = 0;
        $stats = Stat::all(array("item_id = ?" => $item->id), array("amount"));
        foreach ($stats as $stat) {
            $earn += $stat->amount;
        }

        $links = Link::count(array("item_id = ?" => $item->id));
        $rpm = RPM::count(array("item_id = ?" => $item->id));

        $view->set("item", $item);
        $view->set("earn", $earn);
        $view->set("links", $links);
        $view->set("rpm", $rpm);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function urlDebugger() {
        $this->seo(array("title" => "URL Debugger", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $url = RequestMethods::get("urld", "http://clicks99.com/");
        $metas = get_meta_tags($url);

        $facebook = new Curl();
        $facebook->get('https://api.facebook.com/method/links.getStats', array(
            'format' => 'json',
            'urls' => $url
        ));
        $facebook->setOpt(CURLOPT_ENCODING , 'gzip');
        $facebook->close();

        $twitter = new Curl();
        $twitter->get('https://cdn.api.twitter.com/1/urls/count.json', array(
            'url' => $url
        ));
        $twitter->setOpt(CURLOPT_ENCODING , 'gzip');
        $twitter->close();

        $view->set("url", $url);
        $view->set("metas", $metas);
        $view->set("facebook", array_values($facebook->response)[0]);
        $view->set("twitter", $twitter->response);
    }

    /**
     * @before _secure
     */
    public function link($date = NULL) {
        $this->JSONview();
        $view = $this->getActionView();

        $link_id = RequestMethods::get("link");
        $link = Link::first(array("id = ?" => $link_id), array("item_id", "id"));
        $result = $link->stat($date);
        
        $view->set("earning", $result["earning"]);
        $view->set("click", $result["click"]);
        $view->set("rpm", $result["rpm"]);
        $view->set("analytics", $result["analytics"]);
        $view->set("link", $link);
    }

    /**
     * @before _secure, changeLayout
     */
    public function logs($action = "", $name = "") {
        $this->seo(array("title" => "Activity Logs", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if ($action == "unlink") {
            $file = APP_PATH ."/logs/". $name . ".txt";
            @unlink($file);
            self::redirect("/analytics/logs");
        }

        $logs = array();
        $path = APP_PATH . "/logs";
        $iterator = new DirectoryIterator($path);

        foreach ($iterator as $item) {
            if (!$item->isDot()) {
                if (substr($item->getFilename(), 0, 1) != ".") {
                    array_push($logs, $item->getFilename());
                }
            }
        }
        arsort($logs);
        $view->set("logs", $logs);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function clicks() {
        $this->seo(array("title" => "Clicks Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $now = strftime("%Y-%m-%d", strtotime('now'));
        $view->set("now", $now);
    }

    /**
     * Today Stats of user
     * @return array earnings, clicks, rpm, analytics
     * @before _secure
     */
    public function stats($created = NULL, $auth = 1, $user_id = NULL, $item_id = NULL) {
        $this->seo(array("title" => "Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $total_click = 0;$earning = 0;$analytics = array();$query = array();
        $rpm = array("IN" => 135, "US" => 220, "CA" => 220, "AU" => 220, "GB" => 220, "NONE" => 80);
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => array());

        is_null($created) ? NULL : $query['created'] = $created;
        is_null($item_id) ? NULL : $query['item_id'] = $item_id;
        if ($auth) {
            $query['user_id'] = (is_null($user_id) ? $this->user->id : $user_id);
        }

        $connection = new Mongo();
        $db = $connection->stats;
        $collection = $db->clicks;

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
            
        }

        if ($total_click > 0) {
            $return = array(
                "click" => round($total_click),
                "rpm" => round($earning*1000/$total_click, 2),
                "earning" => round($earning, 2),
                "analytics" => $analytics
            );
        }

        $view->set("stats", $return);
    }

    /**
     * Analytics of Single Campaign Datewise
     * @return array earnings, clicks, rpm, analytics
     * @before _secure
     */
    public function campaign($created = NULL, $item_id = NULL) {
        $this->seo(array("title" => "Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $total_click = 0;$earning = 0;$analytics = array();$query = array();$i = array();
        $rpm = array("IN" => 135, "US" => 220, "CA" => 220, "AU" => 220, "GB" => 220, "NONE" => 80);
        $return = array("click" => 0, "rpm" => 0, "earning" => 0, "analytics" => array());

        is_null($created) ? NULL : $query['created'] = $created;
        if (is_null($item_id)) {
            $items = Item::all(array("user_id = ?" => $this->user->id), array("id"));
            foreach ($items as $item) {
                $i[] = $item->id;
            }
            $query['item_id'] = array('$in' => $i);
        } else {
            $query['item_id'] = $item_id;
        }
        
        $connection = new Mongo();
        $db = $connection->stats;
        $collection = $db->clicks;

        $cursor = $collection->find($query, array("click", "country"));
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
            
        }

        if ($total_click > 0) {
            $return = array(
                "click" => round($total_click),
                "rpm" => round($earning*1000/$total_click, 2),
                "earning" => round($earning, 2),
                "analytics" => $analytics
            );
        }

        $view->set("stats", $return);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function verify($user_id) {
        $this->seo(array("title" => "Verify Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $stats = Stat::all(array("user_id = ?" => $user_id), array("*"), "amount", "desc", $limit, $page);
        
        $view->set("stats", $stats);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", Stat::count(array("user_id = ?" => $user_id)));
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function delduplicate($stat_id) {
        $this->noview();
        
        $stat = Stat::first(array("id = ?" => $stat_id));
        $link = Link::first(array("id = ?" => $stat->link_id));
        $account = Account::first(array("user_id = ?" => $stat->user_id));
        if ($link->delete()) {
            $account->balance -= $stat->amount;
            $account->save();
            $stat->delete();
        }
        
        self::redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * @before _secure, changeLayout
     */
    public function reports() {
        $this->noview();
        $date = date('Y-m-d', strtotime("now"));
        $yesterday = date('Y-m-d', strtotime("-1 Day"));
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=report{$this->user->id}_{$yesterday}.csv");
        $output = fopen('php://output', 'w');

        fputcsv($output, array('Link', 'Clicks', 'Amount', 'RPM', 'Earning'));
        $m = Registry::get("MongoDB")->urls;
        $links = $m->find(array('user_id' => $this->user->id, "created < ?" => $now));
        foreach ($links as $key => $value) {
            $link = Link::first(array("id = ?" => $value["link_id"]), array("short", "id", "item_id"));
            $stat = Stat::first(array("link_id = ?" => $value["link_id"]), array("click", "amount", "rpm"));
            if (isset($stat)) {
                fputcsv($output, array($link->short, $stat->click, $stat->amount, $stat->rpm, "Added"));
            } else {
                $data = $link->stat($yesterday);
                fputcsv($output, array($link->short, $data["click"], $data["amount"], $data["rpm"], "Not Added, Sessions less than 10"));
            }
        }
    }
}
