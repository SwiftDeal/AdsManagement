<?php

/**
 * Description of analytics
 *
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use \Curl\Curl;
use ClusterPoint\DB as DB;

class Analytics extends Admin {
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function googl() {
        $this->seo(array("title" => "shortURL Analytics", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::get("shortURL")) {
            $shortURL = RequestMethods::get("shortURL");
            $googl = Registry::get("googl");
            $object = $googl->analyticsFull($shortURL);

            $longUrl = explode("?item=", $object->longUrl);
            if($longUrl) {
                $str = base64_decode($longUrl[1]);
                $datas = explode("&", $str);
                foreach ($datas as $data) {
                    $property = explode("=", $data);
                    $item[$property[0]] = $property[1];
                }
            }

            $view->set("shortURL", $shortURL);
            $view->set("googl", $object);
            $view->set("item", $item);
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
        $earnings = Earning::all(array("item_id = ?" => $item->id), array("amount"));
        foreach ($earnings as $earning) {
            $earn += $earning->amount;
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

        $url = RequestMethods::get("urld", "http://likesbazar.in/");
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

    protected function stat($link, $duration = "allTime") {
        $domain_click = 0;
        $country_click = 0;
        $earning = 0;
        $verified = 0;
        $code = "";
        $country_code = array("IN", "US", "CA", "AU","GB");

        $time = ($duration == "day") ? time() - 24*60*60 : 0;
        $clusterpoint = Link::clusterpoint($link->item_id, $link->user_id, $time);
        if ($clusterpoint) {
            $verified = $clusterpoint->click;
        }
        
        $stat = Link::googl($link->short);
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

            $countries = $googl->countries;
            $rpms = RPM::first(array("item_id = ?" => $link->item_id), array("value"));
            $rpm = json_decode($rpms->value);
            foreach ($countries as $country) {
                if (in_array($country->id, $country_code)) {
                    $code = $country->id;
                    $earning += ($rpm->$code)*($country->count)/1000;
                    $country_click += $country->count;
                }
            }
            if($total_click > $country_click) {
                echo ($country_click);
                $earning += ($rpm->NONE)*($total_click - $country_click)/1000;
            }

            $return = array(
                "click" => $total_click,
                "rpm" => round(($earning*1000)/($total_click), 2),
                "earning" => round($earning, 2),
                "verified" => $verified
            );

        }

        return $return;
    }

    /**
     * @before _secure
     */
    public function link($duration = "allTime") {
        $this->JSONview();
        $view = $this->getActionView();

        $shortURL = RequestMethods::get("shortURL");
        $link = Link::first(array("short = ?" => $shortURL), array("item_id", "short", "user_id"));
        $result = $this->stat($link, $duration);
        
        $view->set("earning", $result["earning"]);
        $view->set("click", $result["click"]);
        $view->set("rpm", $result["rpm"]);
        $view->set("verified", $result["verified"]);
    }

    /**
     * @before _secure
     */
    public function realtime($duration = "allTime") {
        $this->JSONview();
        $view = $this->getActionView();

        $earnings = 0;
        $clicks = 0;
        $verified = 0;
        $links = Link::all(array("user_id = ?" => $this->user->id, "created >= ?" => date('Y-m-d', strtotime("-3 day"))), array("short", "item_id", "user_id"));
        foreach ($links as $link) {
            $result = $this->stat($link, $duration);
            if ($result) {
                $clicks += $result["click"];
                $earnings += $result["earning"];
                $verified += $result["verified"];
            }
            $result = 0;
        }

        $view->set("avgrpm", round(($earnings*1000)/($clicks), 2));
        $view->set("earnings", $earnings);
        $view->set("clicks", $clicks);
        $view->set("verified", $verified);
    }
    
}
