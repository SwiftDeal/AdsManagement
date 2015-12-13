<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Auth {

    public function index() {
        $this->noview();
        $this->verify();
        echo "Completed";
    }
    
    protected function verify() {
        $startdate = date('Y-m-d', strtotime("-7 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created <= ?" => $enddate
        );
        $links = Link::all(array(), array("id", "short", "item_id", "user_id"));

        $counter = 0;
        $googl = Framework\Registry::get("googl");
        foreach ($links as $link) {
            $object = $googl->analyticsFull($link->short);
            $count = $object->analytics->allTime->shortUrlClicks;
            $verified = 0;
            
            if ($count > 15) {
                $stat = $this->saveStats($object, $link, $count);
                $this->saveEarnings($link, $count, $stat, $object);

                /*//sleep the script
                if ($counter == 10) {
                    sleep(1);
                    $counter = 0;
                }
                ++$counter;*/
            }
        }
    }

    protected function saveStats($object, $link, $count) {
        $stat = new Stat(array(
            "user_id" => $link->user_id,
            "link_id" => $link->id,
            "verifiedClicks" => $count,
            "shortUrlClicks" => $object->analytics->allTime->shortUrlClicks
        ));
        $stat->save();
        return $stat;
    }
    
    protected function saveEarnings($link, $count, $stat, $object) {
        $domain_click = 0;
        $country_click = 0;
        $revenue = 0;
        $verified = 0;
        $code = "";
        $country_code = array("IN", "US", "CA", "AU","GB");
        
        $googl = $object->analytics->allTime;
        $total_click = $googl->shortUrlClicks;

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
                $revenue += ($rpm->$code)*($country->count)/1000;
                $country_click += $country->count;
            }
        }

        if($total_click > $country_click) {
            $revenue += ($rpm->NONE)*($total_click - $country_click)/1000;
        }

        $avgrpm = round(($revenue*1000)/($total_click), 2);
        $earning = new Earning(array(
            "item_id" => $link->item_id,
            "link_id" => $link->id,
            "amount" => $revenue,
            "user_id" => $link->user_id,
            "stat_id" => $stat->id,
            "rpm" => $avgrpm,
            "live" => 1
        ));
        $earning->save();
    }
}
