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
    }
    
    protected function verify() {
        $counter = 0;
        $startdate = date('Y-m-d', strtotime("-7 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created <= ?" => $enddate
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));

        foreach ($links as $link) {
            $data = $link->stat();
            if ($data["click"] > 20) {
                $stat = $this->saveStats($data, $link);
                $this->saveEarnings($link, $stat, $data);

                //sleep the script
                if ($counter == 10) {
                    sleep(1);
                    $counter = 0;
                }
                ++$counter;
            }
        }
    }

    protected function saveStats($data, $link) {
        $now = date('Y-m-d', strtotime("now"));
        $exist = Stat::first(array("link_id = ?" => $link->id, "created > ?" => $now));
        if(!$exist) {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "verifiedClicks" => $data["verified"],
                "shortUrlClicks" => $data["click"]
            ));
            $stat->save();
            return $stat;
        } else{
            return $exist;
        }
    }
    
    protected function saveEarnings($link, $stat, $data) {
        $now = date('Y-m-d', strtotime("now"));
        $exist = Earning::first(array("link_id = ?" => $link->id, "created > ?" => $now));
        if(!$exist) {
            $earning = new Earning(array(
                "item_id" => $link->item_id,
                "link_id" => $link->id,
                "amount" => $data["earning"],
                "user_id" => $link->user_id,
                "stat_id" => $stat->id,
                "rpm" => $data["rpm"],
                "live" => 1
            ));
            $earning->save();
        }
    }

}
