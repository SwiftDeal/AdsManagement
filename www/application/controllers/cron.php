<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Shared\Controller {

    public function __construct($options = array()) {
        parent::__construct($options);
        if (php_sapi_name() != 'cli') {
            $this->redirect("/404");
        }
    }

    public function index() {
        $this->noview();
        $this->log("Publisher CRON Started");
        $this->_publisher();
        $this->log("CRON Ended");

        //$this->log("Advertiser CRON Started");
        //$this->_advertiser();
        
        // $this->log("Advertiser Analytics Cron Ended");
        // $this->_ga();
        // $this->log("Advertiser Analytics Cron Ended");
        
        //$this->log("Advertiser CRON Ended");
    }

    protected function _advertiser() {
        $yesterday = date('Y-m-d', strtotime("-1 day"));
        $today = date('Y-m-d', strtotime("now"));
        $accounts = array();

        $items = Item::all(array("live = ?" => true), array("id", "commission", "user_id"));
        foreach ($items as $item) {
            $data = $item->stats($yesterday);
            if ($data["click"] > 1) {
                $insight = $this->_insight($data, $item, $today);
                echo "<pre>", print_r($insight), "</pre>";
                if (array_key_exists($insight->user_id, $accounts)) {
                    $accounts[$insight->user_id] += -($data["earning"])*(1+$item->commission/100);
                } else {
                    $accounts[$insight->user_id] = -($data["earning"])*(1+$item->commission/100);
                }
                //sleep the script
                sleep(1);
            }
        }


        echo "<pre>", print_r($accounts), "</pre>";
        /*sleep(10);
        if (!empty($accounts)) {
            $this->log("Account Started");
            $this->_account($accounts);
            $this->log("Account Ended");
        }*/
    }

    protected function _insight($data, $item, $today) {
        $insight = Insight::first(array("item_id = ?" => $item->id));
        if(!$insight) {
            $insight = new Insight(array(
                "user_id" => $item->user_id,
                "item_id" => $item->id,
                "click" => $data["click"],
                "amount" => $data["earning"],
                "rpm" => $data["rpm"],
                "live" => 1,
                "updated" => $today
            ));
            //$insight->save();
            $output = "New Insight {$insight->id} - Done";
        } else {
            $modified = strtotime($insight->updated);
            $output = "Insight {$insight->id} - Dropped";
            if($modified < strtotime($today)) {
                $insight->click += $data["click"];
                $insight->amount += $data["earning"];
                $insight->rpm = $data["rpm"];
                $insight->updated = $today;
                //$insight->save();
                $output = "Updated Insight {$insight->id} - Done";
            }
        }

        //$this->log($output);
        return $insight;
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
                //sleep the script
                sleep(1);
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
        $cpc = (array) json_decode($advertiser->cpc);
        $clicks = [];
        
        foreach ($results as $r) {
            $countryCode = $r['countryIsoCode'];
            if (array_key_exists($countryCode, $clicks)) {
                $d = (int) $clicks[$countryCode] + (int) $r['sessions'];
            } else {
                $d = (int) $r['sessions'];
            }
            $clicks[$countryCode] = $d;
        }

        $spent = 0; $clicks = 0; $rpm = 0;
        foreach ($clicks as $key => $value) {
            $spent += (int) $cpc[$key] * $value;
            $clicks += $value;
            $rpm += $cpc[$key];
        }
        $insight = Insight::first(["user_id = ?" => $advertiser->user_id]);
        if (!$insight) {
            $insight = new Insight([
                "user_id" => $advertiser->user_id
            ]);
        }
        $insight->click = $clicks;
        $insight->amount = $spent;
        $insight->cpc = ($spent * 1000) / $clicks;
        $insight->save();
    }

    /**
     * Updated Bounce Rate for each publisher
     * @param array $results
     */
    protected function _gaPublish($results) {
        $users = [];
        foreach ($results as $r) {
            $key = $r['source'];
            if (array_key_exists($key, $users)) {
                $d = array_merge($users[$key], $r);
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
        }
    }

    /**
     * Fetches Google Analytics data for each advertiser
     */
    protected function _ga() {
        try {
            $advertiser = Advert::all(["live = ?" => true]);
            foreach ($advertiser as $a) {
                if (!$a->gatoken) {
                    continue;
                }
                $client = Shared\Services\GA::client($a->gatoken);

                $user = Framework\ArrayMethods::toObject([
                    "id" => $a->user_id
                ]);
                $opts = [
                    "start" => "yesterday",
                    "end" => "yesterday"
                ];
                $results = Shared\Services\GA::update($client, $user, ['action' => 'addition', 'start' => 'yesterday', 'end' => 'yesterday']);

                $this->_gaInsight($results, $advertiser);
                $this->_gaPublish($results);

                sleep(1);
            }
        } catch (\Exception $e) {
            $this->log("Google Analytics Cron Failed (Error: " . $e->getMessage(). " )");
        }
    }
}
