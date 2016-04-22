<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Shared\Controller {

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->noview();
        if (php_sapi_name() != 'cli') {
            $this->redirect("/404");
        }
    }

    public function index($type = "daily") {
        switch ($type) {
            case 'hourly':
                $this->_hourly();
                break;

            case 'daily':
                $this->_daily();
                break;

            case 'weekly':
                $this->_weekly();
                break;

            case 'monthly':
                $this->_monthly();
                break;
        }
    }

    protected function _hourly() {
        // implement
    }

    protected function _daily() {
        $this->log("Publisher CRON Started");
        $this->_publisher();
        $this->log("CRON Ended");

        //$this->log("Advertiser CRON Started");
        //$this->_advertiser();
        
        // $this->log("Analytics Cron Ended");
        // $this->_ga();
        // $this->log("Analytics Cron Ended");
        
        //$this->log("Advertiser CRON Ended");
    }

    protected function _weekly() {
        // implement
    }

    protected function _monthly() {
        // implement
    }

    protected function _publisher() {
        $this->log("LinksTracking Started");
        $this->ctracker();
        $this->log("LinksTracking Ended");
        
        $this->log("Password Meta Started");
        $this->passwordmeta();
        $this->log("Password Meta Ended");
    }

    protected function ctracker() {
        $date = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => date('Y-m-d', strtotime("-20 day")),
            "created < ?" => date('Y-m-d', strtotime("now"))
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));
        $accounts = $this->verify($date, $links);
        
        sleep(10);
        if (!empty($accounts)) {
            $this->log("Account Started");
            $this->_account($accounts);
            $this->log("Account Ended");
        }
    }
    
    protected function verify($today, $links) {
        $accounts = array();
        $yesterday = date('Y-m-d', strtotime($today . " -1 day"));

        foreach ($links as $link) {
            $data = $link->stat($yesterday);
            if ($data["click"] > 10) {
                $stat = $this->_stat($data, $link, $today);
                if (array_key_exists($stat->user_id, $accounts)) {
                    $accounts[$stat->user_id] += $data["earning"];
                } else {
                    $accounts[$stat->user_id] = $data["earning"];
                }
                sleep(1); //sleep the script
            }
        }
        return $accounts;
    }

    protected function _stat($data, $link, $today) {
        $stat = Stat::first(array("link_id = ?" => $link->id));
        if(!$stat) {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "item_id" => $link->item_id,
                "click" => $data["click"],
                "amount" => $data["earning"],
                "rpm" => $data["rpm"],
                "live" => 1,
                "updated" => $today
            ));
            $stat->save();
            $output = "New Stat {$stat->id} - Done";
        } else {
            $modified = strtotime($stat->updated);
            $output = "{$stat->id} - Dropped";
            if($modified < strtotime($today)) {
                $stat->click += $data["click"];
                $stat->amount += $data["earning"];
                $stat->rpm = $data["rpm"];
                $stat->updated = $today;
                $stat->save();
                $output = "Updated Stat {$stat->id} - Done";
            }
        }

        $this->log($output);
        return $stat;
    }

    protected function _account($accounts, $ref="linkstracking") {
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
                $account->balance += $value;
                $account->save();
            }
            $transaction = new Transaction(array(
                "user_id" => $key,
                "amount" => $value,
                "ref" => $ref
            ));
            $transaction->save();
        }
    }

    protected function passwordmeta() {
        $now = date('Y-m-d', strtotime("now"));
        $meta = Meta::all(array("property = ?" => "resetpass", "created < ?" => $now));
        foreach ($meta as $m) {
            $m->delete();
        }
    }

    protected function fraud() {
        $this->log("Fraud Started");
        $now = date('Y-m-d', strtotime("now"));
        $fp = fopen(APP_PATH . "/logs/fraud-{$now}.csv", 'w');
        fputcsv($fp, array("USER_ID", "STAT_ID", "LINK_ID"));

        $users = Stat::all(array(), array("DISTINCT user_id"), "amount", "DESC");
        foreach ($users as $user) {
            $this->log("Checking User - {$user->user_id}");
            $stats = Stat::all(array("user_id = ?" => $user->user_id), array("id", "link_id", "user_id"));
            foreach ($stats as $stat) {
                if ($stat->is_bot()) {
                    fputcsv($fp, array($stat->user_id, $stat->id, $stat->link_id));
                    $this->log("Fraud - {$stat->link_id}");
                }
                sleep(1);
            }
        }
        fclose($fp);
        $this->log("Fraud Ended");
    }

    /**
     * Updates the google analytics insights for each advertiser
     * @param array $results
     * @param object $advertiser \Advert
     */
    protected function _gaInsight($results, $advertiser) {
        $cpcs = json_decode($advertiser->cpc);
        if (!$cpcs) {
            $this->log("CPC not added for advertiser: " . $advertiser->user_id);
            return;
        } else {
            $cpcs = (array) $cpcs;
        }

        $clicks = [];
        
        foreach ($results as $r) {
            unset($r['_id']);
            $countryCode = $r['countryIsoCode'];
            if (array_key_exists($countryCode, $clicks)) {
                $d = (int) $clicks[$countryCode] + (int) $r['sessions'];
            } else {
                $d = (int) $r['sessions'];
            }
            $clicks[$countryCode] = $d;
        }

        $spent = 0.0; $click_count = 0; $rpm = 0;
        foreach ($clicks as $key => $value) {
            $cpc = (array_key_exists($key, $cpcs)) ? $cpcs[$key] : $cpcs["NONE"];
            $spent += (float) ($cpc * $value / 1000);
            $click_count += $value;
            $rpm += $cpc;
        }
        $insight = Insight::first(["user_id = ?" => $advertiser->user_id, "created = ?" => date("Y-m-d")]);
        if (!$insight) {
            $insight = new Insight([
                "user_id" => $advertiser->user_id,
                "created" => date('Y-m-d'),
                "live" => 1
            ]);
        }
        $insight->click = $click_count;
        $insight->amount = $spent;
        if ($click_count == 0) $click_count = 1;
        $insight->cpc = ($spent * 1000) / $click_count;
        $insight->save();

        return $insight;
    }

    /**
     * Updated Bounce Rate for each publisher
     * @param array $results
     */
    protected function _gaPublish($results) {
        $users = [];
        foreach ($results as $r) {
            $key = $r['source']; unset($r['_id']);
            if (array_key_exists($key, $users)) {
                $d = array_merge($users[$key], [$r]);
            } else {
                $d = [$r];
            }
            $users[$key] = $d;
        }

        foreach ($users as $key => $value) {
            $publish = Publish::first(["user_id = ?" => $key]);
            if (!$publish) continue;

            $bounceRate = 0.0; $c = 0;
            foreach ($value as $v) {
                $bounceRate += (float) $v['bounceRate'];
                $c++;
            }
            if ($c == 0) $c = 1; // Error checking
            $publish->bouncerate = $bounceRate / $c;
            $publish->save();
            $this->log("Updated publisher: ". $publish->user_id);
            usleep(1000);
        }
    }

    /**
     * Fetches Google Analytics data for each advertiser
     */
    public function _ga() {
        $insights = [];
        try {
            $advertiser = Advert::all(["user_id = ?" => 1]);
            foreach ($advertiser as $a) {
                if (!$a->gatoken) {
                    continue;
                }
                $client = Shared\Services\GA::client($a->gatoken);

                $user = Framework\ArrayMethods::toObject([
                    "id" => $a->user_id
                ]);
                $opts = [
                    "start" => "2016-02-14",
                    "end" => "2016-04-22",
                    "action" => "addition"
                ];
                $results = Shared\Services\GA::update($client, $user, $opts);
                
                $insights[$a->user_id] = $this->_gaInsight($results, $a);
                $this->_gaPublish($results);
                $this->log("Updated advertiser insights: ". $a->user_id);
                usleep(1000);
            }

            $accounts = [];
            foreach ($insights as $i) {
                if (!is_object($i)) continue;
                $accounts[$i->user_id] = -($i->amount);
            }
            $this->_account($accounts);
        } catch (\Exception $e) {
            $this->log("Google Analytics Cron Failed (Error: " . $e->getMessage(). " )");
        }
    }
}
