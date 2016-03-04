<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Shared\Controller {

    public function index() {
        $this->noview();
        $this->log("CRON Started");
        $accounts = $this->verify();
        $this->log("CRON Ended");

        if (!empty($accounts)) {
            $this->log("Account Started");
            $this->saveAccount($accounts);
            $this->log("Account Ended");
        }

        $this->log("Password Meta Started");
        $this->passwordmeta();
        $this->log("Password Meta Ended");
    }
    
    protected function verify() {
        $accounts = array();
        $yesterday = strftime("%Y-%m-%d", strtotime('-1 day'));
        $startdate = date('Y-m-d', strtotime("-20 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created < ?" => $enddate
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));

        foreach ($links as $link) {
            $data = $link->stat($yesterday);
            if ($data["click"] > 30) {
                $stat = $this->saveStats($data, $link);
                if (array_key_exists($stat->user_id, $accounts)) {
                    $accounts[$stat->user_id] += $data["earning"];
                } else {
                    $accounts[$stat->user_id] = $data["earning"];
                }

                //sleep the script
                sleep(1);
            }
        }

        return $accounts;
    }

    protected function saveStats($data, $link) {
        $stat = Stat::first(array("link_id = ?" => $link->id));
        if(!$stat) {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "item_id" => $link->item_id,
                "click" => $data["click"],
                "amount" => $data["earning"],
                "rpm" => $data["rpm"],
                "live" => 1
            ));
            $stat->save();
        } else {
            $today =strtotime(date('Y-m-d', strtotime("now")));
            $modified = strtotime($stat->modified);

            if($modified < $today) {
                $stat->click += $data["click"];
                $stat->amount += $data["earning"];
                $stat->rpm = $data["rpm"];
                $stat->save();
            }
        }
        
        $output = "{$stat->id} - Done";
        $this->log($output);

        return $stat;
    }

    protected function saveAccount($accounts) {
        foreach ($accounts as $key => $value) {
            $account = Account::first(array("user_id = ?" => $key));
            if (!$account) {
                $account = new Account(array(
                    "user_id" => $key,
                    "balance" => $value,
                    "live" => 1
                ));
                $account->save();
            } else {
                $today =strtotime(date('Y-m-d', strtotime("now")));
                $modified = strtotime($account->modified);

                if($modified < $today) {
                    $account->balance += $value;
                    $account->save();
                }
            }
            $transaction = new Transaction(array(
                "user_id" => $key,
                "amount" => $value,
                "ref" => "linkstracking"
            ));
            $transaction->save();
        }
    }

    protected function reset() {
        $db = Framework\Registry::get("database");
        $db->sync(new Stat);
        $links = Link::all(array(), array("id", "short", "item_id", "user_id"));
        $startdate = date('Y-m-d', strtotime("-6 day"));
        $enddate = date('Y-m-d', strtotime("-1 day"));
        $diff = date_diff(date_create($startdate), date_create($enddate));
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($startdate . " +{$i} day"));
            foreach ($links as $link) {
                $data = $link->stat($date);
                if ($data["click"] > 30) {
                    $this->saveStats($data, $link);

                    //sleep the script
                    sleep(1);
                }
            }
        }
    }

    protected function passwordmeta() {
        $now = date('Y-m-d', strtotime("now"));
        $meta = Meta::all(array("property = ?" => "resetpass", "created < ?" => $now));
        foreach ($meta as $m) {
            $m->delete();
        }
    }
    
}
