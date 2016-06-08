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
    public function link($date = NULL) {
        $this->JSONview();
        $view = $this->getActionView();

        $link_id = RequestMethods::get("link");
        $link = Link::first(array("id = ?" => $link_id), array("item_id", "id", "user_id"));
        if (!$link || $link->user_id != $this->user->id) {
            $this->redirect("/404");
        }
        $result = $link->stat($date);
        
        $view->set("earning", $this->user->convert($result["earning"]));
        $view->set("click", $result["click"]);
        $view->set("rpm", $this->user->convert($result["rpm"]));
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
            $this->redirect("/analytics/logs");
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
    public function campaign($created = NULL, $item_id = NULL) {
        $this->seo(array("title" => "Stats", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $total_click = 0;$spent = 0;$analytics = array();$query = array();$i = array();
        $return = array("click" => 0, "cpc" => 0, "spent" => 0, "analytics" => array());
        
        $advert = Advert::first(["user_id = ?" => $this->user->id], ["cpc"]);
        $cpc = json_decode($advert->cpc, true);
        if ($this->validateDate($created)) {
            $query['created'] = $created;
        }
        if (is_null($item_id)) {
            $items = Item::all(array("user_id = ?" => $this->user->id), array("id"));
            foreach ($items as $item) {
                $i[] = $item->id;
            }
            $query['item_id'] = array('$in' => $i);
        } else {
            $query['item_id'] = $item_id;
        }
        
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
                "cpc" => $this->user->convert(round($spent*1000/$total_click, 2)),
                "spent" => $this->user->convert(round($spent, 2)),
                "analytics" => $analytics
            );
        }

        $view->set("stats", $return);
        $view->set("query", $query);
        $view->set("cpc", $cpc);
    }

    /**
     * @before _secure, changeLayout
     */
    public function reports() {
        $this->noview();
        $date = date('Y-m-d', strtotime("now"));
        $yesterday = date('Y-m-d', strtotime("-1 Day"));
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
                if ($link) {
                    $data = $link->stat($yesterday);
                    fputcsv($output, array($link->short, $data["click"], $data["amount"], $data["rpm"], "Not Added, Sessions less than 10"));
                }
            }
        }
        header('Content-Description: File Transfer');
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=report{$this->user->id}_{$yesterday}.csv");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $done = fclose($output);
        exit;
    }

    /**
     * Shortens the url for publishers
     * @before _secure, _layout
     */
    public function shortenURL() {
        $this->JSONview();
        $view = $this->getActionView();
        $link = new Link(array(
            "user_id" => $this->user->id,
            "short" => "",
            "item_id" => RequestMethods::get("item"),
            "live" => 1
        ));
        $link->save();
        
        $item = Item::first(array("id = ?" => RequestMethods::get("item")), array("url", "title", "image", "description"));
        $m = Registry::get("MongoDB")->urls;
        $doc = array(
            "link_id" => $link->id,
            "item_id" => RequestMethods::get("item"),
            "user_id" => $this->user->id,
            "url" => $item->url,
            "title" => $item->title,
            "image" => $item->image,
            "description" => $item->description,
            "created" => date('Y-m-d', strtotime("now"))
        );
        $m->insert($doc);

        $d = Meta::first(array("user_id = ?" => $this->user->id, "property = ?" => "domain"), array("value"));
        if($d) {
            $longURL = $d->value . '/' . base64_encode($link->id);
        } else {
            $domains = $this->target();
            $k = array_rand($domains);
            $longURL = RequestMethods::get("domain", $domains[$k]) . '/' . base64_encode($link->id);
        }

        //$link->short = $this->_bitly($longURL);
        $link->short = $longURL;
        $link->save();

        $view->set("shortURL", $link->short);
        $view->set("link", $link);
    }

}
