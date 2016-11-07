<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Shared\Services\Db;
use Shared\Services\Performance as Perf;
use Framework\ArrayMethods as ArrayMethods;

class Test extends Auth {
    /**
     * @before _admin
     */
    public function index() {
        $this->seo(array("title" => "Manage Account")); $i = 0; $view = $this->getActionView();
        /*$ads = Ad::all(['_id' => '57eb8aa51d41c8676b74b044']);
        foreach ($ads as $a) {
            foreach ($a->category as $id) {
                $cat = Category::first(['_id' => $id]);

                $catCol = Registry::get("MongoDB")->categories;
                if (!$cat) {
                    // $catCol->insertOne([
                    //     '_id' => Db::convertType($id, 'id'),
                    //     'name' => 'hot',
                    //     'org_id' => Db::convertType($a->org_id, 'id')
                    // ]);
                } else {
                    $cat->created = date('Y-m-d');
                    $cat->name = 'hot';
                    // $cat->save();
                }
            }
            
        }*/
        $email = '';
        // $user = User::first(['email' => $email]);
        // $this->setUser($user);
    }

    /**
     * @before _admin
     */
    public function sendMail() {
        $this->noview();
        $cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;

        try {
            \Shared\Services\Smtp::sendMail($this->org, [
                'template' => 'testmail',
                'user' => $this->user,
                'to' => [$cf->api->email],  // this argument expects array value
                'subject' => "Testing Mail using SMTP"
            ]);
            var_dump('Mail sent');
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    /**
     * @before _admin
     */
    public function publishers() {
        $this->JSONview(); $view = $this->getActionView();
        $date = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-1 day')));

        $users = \User::all(['type' => 'publisher', 'org_id' => $this->org->_id], ['_id', 'name']);
        $in = Utils::mongoObjectId(array_keys($users));

        $query = [
            "pid" => ['$in' => $in],
            "created" => Db::dateQuery($date, $date)
        ];

        $records = Db::query('Click', $query, ['adid', 'pid', 'is_bot', 'ipaddr', 'referer']);

        $classify = \Click::classify($records, 'pid');
        $pubClicks = [];
        foreach ($classify as $pid => $pClicks) {
            $orig = count($pClicks); $javascript_filtered = 0;
            foreach ($pClicks as $c) {
                if (!$c->is_bot) {
                    $javascript_filtered++;
                }
            }

            $uniqClicks = \Click::checkFraud($pClicks, $this->org);
            $referer_filtered = count($uniqClicks);

            $pubClicks[$pid] = ArrayMethods::toObject([
                'name' => $users[$pid]->name,
                'unverified' => $orig,
                'referer_filtered' => $referer_filtered,
                'javascript_filtered' => $javascript_filtered
            ]);
        }

        $view->set('publishers', $pubClicks);
    }

    public function generateBills() {
        $this->noview();
        $orgs = Organization::all(["live = ?" => true]);
        foreach ($orgs as $org) {
            $imp_cost = 0; $click_cost = 0;
            $month_ini = new DateTime("first day of last month");
            $month_end = new DateTime("last day of last month");
            
            $start = $month_ini->format('Y-m-d');
            $end = $month_end->format('Y-m-d');

            $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

            // find advertiser performances to get clicks and impressions
            $performances = \Performance::overall(
                $dateQuery,
                User::all(['org_id' => $org->_id, 'type' => 'advertiser'], ['_id'])
            );
            $clicks = $performances['total_clicks'];
            if ($clicks > 1000) {
                $click_cost = 0.001*$clicks*$org->meta["bill"]["tcc"];
            }
            $impressions = $performances['total_impressions'];
            if ($impressions > 1000000) {
                $imp_cost = 0.001*0.001*$impressions*$org->meta["bill"]["mic"];
            }
            $bill = new Bill([
                "org_id" => $org->id,
                "impressions" => $impressions,
                "clicks" => $clicks,
                "mic" => $org->meta["bill"]["mic"],
                "tcc" => $org->meta["bill"]["tcc"],
                "amount" => $click_cost + $imp_cost,
                "live" => false
            ]);
            echo "<pre>", print_r($bill), "</pre>";
            /*$bill->save();
            $user = User::first(["org_id = ?" => $org->id, "type = ?" => "admin"]);
            Mail::send([
                'user' => $user,
                'bill' => $bill,
                'template' => 'adminBilling',
                'subject' => 'Billing at vNative',
                'click_cost' => $click_cost,
                'imp_cost' => $imp_cost,
                'org' => $org
            ]);*/
        }
    }
}