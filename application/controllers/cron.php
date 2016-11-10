<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Services\Db as Db;
use Shared\Services\User as Usr;
use Shared\Services\Performance as Perf;

class Cron extends Shared\Controller {

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->noview();
        if (php_sapi_name() != 'cli') {
            $this->_404();
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
        $this->importCampaigns();
        $this->_contests();
        $this->widgets();
        $this->_settings();
    }

    protected function _weekly() {
        // implement
    }

    /**
     * Run on 1st of every month
     */
    protected function _monthly() {
        $this->generateBills();
    }

    protected function _daily() {
        $this->log("CRON Started");
        $this->log('Starting Memory at: ' . memory_get_usage());

        $this->_performance();
        $this->log('Peak Memory at: ' . memory_get_peak_usage());
        $this->_invoice();
        $this->_webPerf();
        $this->_rssFeed();

        // $this->_test();
    }

    protected function _test() {
        $start = date('Y-m-d', strtotime('-1 day'));
        $end = date('Y-m-d', strtotime('now'));

        $diff = date_diff(date_create($start), date_create($end));
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($start . " +{$i} day"));
            var_dump($date);

            // $this->_performance($date);
            // $this->_webPerf($date);
        }
    }

    protected function _settings() {
        // The Model should handle its cron tasks - hourly, daily
        \Ad::hourly();
        \Click::hourly();
        \User::hourly();
    }

    public function widgets() {
        $this->log("Widgets Started");
        $start = $end = date('Y-m-d');
        $dateQuery = Utils::dateQuery($start, $end);

        $orgs = Organization::all(["live = ?" => true]);
        foreach ($orgs as $org) {
            if (!array_key_exists("widgets", $org->meta)) continue;

            $pubs = User::all(["org_id = ?" => $org->_id, "type = ?" => "publisher"], ["_id", "username"]);
            $in = array_keys($pubs);
            
            $records = Db::query('Click', [
                "created" => Db::dateQuery($start, $end),
                "is_bot" => false, "pid" => ['$in' => $in]
            ], ['adid', 'pid']);

            $adClicks = []; $pubClicks = [];
            foreach ($records as $r) {
                $c = (object) $r;

                $adid = Utils::getMongoID($c->adid);
                $pid = Utils::getMongoID($c->pid);

                if (!isset($adClicks[$adid])) {
                    $adClicks[$adid] = 1;
                } else {
                    $adClicks[$adid]++;
                }

                if (!isset($pubClicks[$pid])) {
                    $pubClicks[$pid] = 1;
                } else {
                    $pubClicks[$pid]++;
                }
            }

            $org->widgets($pubClicks, $adClicks, $pubs);
            $this->log("Widget Saved for Org: " . $org->name);
        }
        $this->log("Widgets Done");
    }

    protected function _performance($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }
        $date = date('Y-m-d', strtotime('-1 day'));
        // find the publishers
        $publishers = \User::all(['type = ?' => 'publisher', 'live = ?' => true], ['_id', 'email', 'meta', 'org_id']);
        $dq = ['start' => $date, 'end' => $date];
        $dateQuery = Utils::dateQuery($dq);
        $start = $dateQuery['start']; $end = $dateQuery['end'];

        // store AD commission info
        $commInfo = []; $orgs = []; $advPerfs = [];
        $advertisers = []; $adsInfo = [];
        foreach ($publishers as $p) {
            $org = \Organization::find($orgs, $p->org_id);
            
            // find the clicks for the publisher
            $clicks = Db::query('Click', [
                'pid' => $p->_id, 'is_bot' => false,
                'created' => Db::dateQuery($date, $date)
            ], ['adid', 'country', 'device', 'os', 'referer']);

            $perf = Performance::exists($p, $date);
            
            // classify the clicks according to AD ID
            $classify = \Click::classify($clicks, 'adid');
            $countryWise = []; $deviceWise = []; $osWise = []; $refWise = [];
            foreach ($classify as $key => $value) {
                $ad = \Ad::find($adsInfo, $key, ['user_id', 'url']);
                $advert = Usr::find($advertisers, $ad->user_id, ['_id', 'meta', 'email', 'org_id']);
                $advertPerf = Usr::findPerf($advPerfs, $advert, $date);

                $countries = \Click::classify($value, 'country');
                foreach ($countries as $country => $records) {
                    $updateData = []; $adClicks = count($records);
                    ArrayMethods::counter($countryWise, $country, $adClicks);

                    $pComm = \Commission::campaignRate($key, $commInfo, $country, array_merge([
                        'type' => 'publisher', 'publisher' => $p
                    ], $dq));
                    
                    $earning = \Ad::earning($pComm, $adClicks); ArrayMethods::copy($earning, $updateData);
                    $perf->update($updateData);

                    $aComm = \Commission::campaignRate($key, $commInfo, $country, array_merge([
                        'type' => 'advertiser'
                    ], $dq));
                    
                    $earning = \Ad::earning($aComm, $adClicks); ArrayMethods::copy($earning, $updateData);
                    $advertPerf->update($updateData);
                }

                $deviceWise = Click::classifyInfo(['clicks' => $value, 'type' => 'device', 'arr' => $deviceWise]);
                $osWise = Click::classifyInfo(['clicks' => $value, 'type' => 'os', 'arr' => $osWise]);
                $refWise = Click::classifyInfo(['clicks' => $value, 'type' => 'referer', 'arr' => $refWise]);
            }

            $msg = 'Performance saved for user: ' . $p->email. ' with clicks: ' . $perf->clicks . ' impressions: ' . $perf->impressions;
            $this->log($msg);

            $splitCountry = ArrayMethods::topValues($countryWise);
            $splitCountry['rest'] = array_sum($countryWise) - array_sum($splitCountry);
            $meta = [
                'country' => $splitCountry,
                'device' => ArrayMethods::topValues($deviceWise, count($deviceWise)),
                'os' => ArrayMethods::topValues($osWise, count($osWise)),
                'referer' => ArrayMethods::topValues($refWise, count($refWise))
            ];
            $perf->meta = $meta;
            $perf->save();
        }

        foreach ($advPerfs as $key => $perf) {
            $msg = 'Saving performance for advertiser: ' . $key . ' with clicks: ' . $perf->clicks . ' earning: '. $perf->revenue .' impressions: ' . $perf->impressions;
            $this->log($msg);
            $perf->save();
        }
    }

    protected function _webPerf($date = null) {
        if (!$date) $date = date('Y-m-d', strtotime('-1 day'));

        $clickCol = Registry::get("MongoDB")->clicks;
        $users = \User::all(['type' => 'advertiser', 'live' => true], ['_id', 'email', 'org_id']);

        $adsInfo = []; $orgs = [];
        foreach ($users as $u) {
            $org_id = Utils::getMongoID($u->org_id);
            if (!array_key_exists($org_id, $orgs)) {
                $org = \Organization::first(['_id' => $org_id], ['url']);
                $orgs[$org_id] = $org;
            } else {
                $org = $orgs[$org_id];
            }

            // find all the ads
            $ads = \Ad::all(['user_id' => $u->_id], ['_id', 'url']);
            // find all the platforms
            $platforms = \Platform::all(['user_id' => $u->_id], ['_id', 'url']);

            foreach ($platforms as $p) {
                $in = [];   // populate ad ids
                foreach ($ads as $a) {
                    $regex = preg_quote($p->url, '.');
                    if (!preg_match('#^'.$regex.'#', $a->url)) {
                        continue;
                    }
                    $in[] = Utils::mongoObjectId($a->_id);
                }

                // new stats
                $stat = \Stat::exists($u, $date);
                $clicks = 0; $revenue = 0.00; $avgCpc = 0.00;

                $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
                $start = $dateQuery['start']; $end = $dateQuery['end'];

                // find all the clicks for the date range
                $records = $clickCol->find([
                    'adid' => ['$in' => $in],
                    'is_bot' => false,
                    'created' => ['$gte' => $start, '$lte' => $end]
                ], ['projection' => ['adid' => 1]]);

                // Now classify the clicks according to the AD
                $classify = \Click::classify($records);

                // foreach ad remove fraud clicks
                foreach ($classify as $key => $value) {
                    if (!array_key_exists($key, $adsInfo)) {
                        $comm = \Commission::first(['ad_id' => $key], ['revenue']);
                        $adsInfo[$key] = $comm;
                    } else {
                        $comm = $adsInfo[$key];
                    }

                    if (is_null($comm->revenue) || !$comm->revenue) {
                        $orate = isset($org->meta['rate']) ? $org->meta['rate'] : 0;
                    } else {
                        $orate = $comm->revenue;
                    }

                    // $uniqClicks = Click::checkFraud($value, $org);
                    // can parse uniqClicks to find traffic from individual devices
                    $adClicks = count($value);
                    
                    $clicks += $adClicks; $revenue += $adClicks * $orate;
                }

                if ($clicks == 0) continue;

                $avgCpc = round($revenue / $clicks, 6);
                $stat->clicks = $clicks; $stat->revenue = round($revenue, 6);
                $stat->cpc = $avgCpc;

                $msg = 'Stats for platform: ' .$p->url . ' Clicks: ' . $stat->clicks;
                $this->log($msg);
                $stat->save();
            }
        }
    }

    /**
     * Process the RSS Feed Urls from Platforms Table and queue the AD
     * urls in the Meta Table for campaign importing
     */
    protected function _rssFeed() {
        // find all the platforms for the advertisers
        $orgs = \Organization::all([], ['_id']);

        foreach ($orgs as $o) {
            $platforms = \Platform::rssFeeds($o);

            // get feed for each platform
            foreach ($platforms as $p) {
                $meta = $p->meta; $rss = $meta['rss'];

                // parsing is stopped
                if (!$rss['parsing']) continue;

                $lastCrawled = null;
                if (isset($rss['lastCrawled'])) {
                    $lastCrawled = $rss['lastCrawled'];
                }
                $result = \Shared\Rss::getFeed($rss['url'], $lastCrawled);
                
                $urls = $result['urls'];
                $rss['lastCrawled'] = $result['lastCrawled'];
                $meta['rss'] = $rss; $p->meta = $meta;
                $p->save();     // save the lastCrawled time

                $user = \User::first(['org_id' => $o->_id, 'type' => 'admin'], ['_id']);
                \Meta::campImport($user->_id, $p->user_id, $result['urls'], $rss['campaign']);
            }
        }
    }

    /**
     * Process the Meta table for campaign urls and create the
     * campaign from the corresponding URL
     */
    protected function importCampaigns() {
        $metas = \Meta::all(['prop = ?' => 'campImport']);

        $users = []; $orgs = [];
        foreach ($metas as $m) {
            $user = Usr::find($users, $m->propid, ['_id', 'org_id', 'email', 'meta']);
            $org = \Organization::find($orgs, $user->org_id);

            $categories = \Category::all(['org_id' => $org->_id], ['_id', 'name']);
            $categories = array_values($categories);
            $category = $categories[array_rand($categories)];

            // fetch ad info foreach URL
            $advert_id = $m->value['advert_id'];
            $comm = $m->value['campaign'] ?? $org->meta;

            if (!isset($comm['model']) || !isset($comm['rate'])) {
                continue;
            }
            $urls = $m->value['urls'];

            foreach ($urls as $url) {
                $ad = \Ad::first(['org_id' => $org->_id, 'url' => $url]);
                if ($ad) continue;  // already crawled URL may be due to failed cron earlier
                
                $data = Utils::fetchCampaign($url);
                $image = Utils::downloadImage($data['image']);
                if (!$image) $image = '';

                $ad = new \Ad([
                    'user_id' => $advert_id,
                    'org_id' => $org->_id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'url' => $url,
                    'image' => $image,
                    'category' => [$category->_id],
                    'type' => 'article',
                    'live' => false,
                    'device' => ['ALL']
                ]);
                if ($ad->validate()) {
                    $ad->save();

                    $rev = $comm['revenue'] ?? (1.25 * (float) $comm['rate']);
                    $commission = new \Commission([
                        'ad_id' => $ad->_id,
                        'model' => $comm['model'],
                        'rate' => $comm['rate'],
                        'revenue' => round($rev, 6),
                        'coverage' => ['ALL']
                    ]);
                    $commission->save();
                } else {
                    var_dump($ad->getErrors());
                }
            }
            $msg = 'Campaigns imported for the user: ' . $user->email;
            $this->log($msg);
            $m->delete();
        }
    }

    public function _contests() {
        $contests = \Contest::all();
        foreach ($contests as $c) {
            // find publishers performances
            $start = $c->start->format('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $dateQuery = Utils::dateQuery($start, $yesterday);

            // whatever may the type be but sort publisher according to clicks
            $pubClicks = [];

            $users = \User::all(['type' => 'publisher', 'org_id' => $c->org_id], ['_id', 'name']);
            foreach ($users as $u) {
                $perf = \Performance::calculate($u, $dateQuery);

                $key = $perf['clicks'];
                if (!array_key_exists($key, $pubClicks)) {
                    $pubClicks[$key] = [];
                }
                $pubClicks[$key][] = sprintf('%s', $u->_id);
            }
            krsort($pubClicks);

            $meta = (is_array($c->meta) ? $c->meta : []);
            if (!isset($meta['condition'])) {
                continue;
            }

            $condition = $meta['condition'];
            switch ($c->type) {
                case 'topEarner':
                    $count = $condition['topEarnerCount'];
                    $i = 0;
                    $condition['winners'] = [];
                    foreach ($pubClicks as $key => $value) {
                        if ($i >= $count) {
                            break;  // we have total topEarners reqd.
                        }
                        foreach ($value as $v) {
                            $condition['winners'][] = $v;
                            $i++;

                            if ($i >= $count) break;
                        }
                    }
                    break;
                
                case 'clickRange':
                    foreach ($pubClicks as $key => $value) {
                        // foreach clickRange check if this key is in range
                        foreach ($condition as &$cond) {
                            $winners = isset($cond['winners']) ? $cond['winners'] : [];

                            $isStart = is_numeric($cond['start']);
                            $isEnd = is_numeric($cond['end']);

                            $start = (int) $cond['start']; $end = (int) $cond['end'];
                            $merge = false;

                            if ($isStart && $isEnd) {
                                if ($start <= $key && $end >= $key) {
                                    $merge = true;
                                }
                            } else if ($isStart && $start <= $key) {
                                $merge = true;
                            } else if ($isEnd && $end >= $key) {
                                $merge = true;
                            }

                            if ($merge) {
                                $winners = array_merge($winners, $value);
                                $winners = array_unique($winners);
                            }

                            $cond['winners'] = $winners;
                        }
                    }
                    break;

            }
            
            $meta['condition'] = $condition;
            $c->meta = $meta;
            var_dump('Saving contest: ' . $c->_id);
            $c->save();
        }
    }

    public function _invoice() {
        $orgs = Organization::all(['live' => true]);
        $today = date('Y-m-d');
        $this->log('Started Invoices');

        foreach ($orgs as $o) {
            $start = date('Y-m-d', strtotime("-1 day"));
            // check auto invoice
            $aff = $o->billing['aff'] ?? [];
            if (!isset($aff['auto']) || !$aff['auto']) continue;
            $this->log('Checking Invoices for Org: ' . $o->name);

            // make invoice for all publishers b/w $start and $today
            $pubs = User::all(['org_id' => $o->_id, 'live' => true, 'type' => 'publisher']);
            foreach ($pubs as $p) {
                $invoice = Invoice::first(['org_id' => $o->_id, 'user_id' => $p->_id], ['created'], 'created', 'desc');
                if ($invoice) { // check no of days
                    $lastCreated = $invoice->created->format('Y-m-d');
                    $diff = date_diff(date_create($lastCreated), date_create($today));

                    if ($diff->d !== (int) $aff['freq']) {
                        continue;
                    }
                    $start = date('Y-m-d', strtotime("-" . $aff['freq'] . " day"));
                } else {
                    $start = $o->created->format('Y-m-d');
                }

                // check if invoice exists for the date range
                $inv = Invoice::exists($p->_id, $start, $today);
                if ($inv) continue;

                $pubPerf = Perf::perf($o, 'publisher', [
                    'publishers' => [$p->_id],
                    'fields' => ['revenue', 'created'],
                    'start' => $start, 'end' => $today
                ]);
                $perf = []; Perf::payout($pubPerf, $perf); $total = Perf::calTotal($perf);
                $payout = $total['payout'] ?? 0; $keys = array_keys($perf);
                if ($payout < $aff['minpay']) continue;

                // create a new invoice
                $inv = new Invoice([
                    'org_id' => $o->_id, 'user_id' => $p->_id,
                    'utype' => $p->type, 'amount' => $payout,
                    'start' => $keys[0], 'end' => end($keys),
                    'live' => false
                ]);

                $inv->save();
                $this->log('Invoice saved for User: ' . $p->name . ' email: ' . $p->email);
            }
        }
        $this->log('End Invoices');
    }

    public function generateBills() {
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
            if ($impressions > 100000) {
                $imp_cost = 0.001*0.001*$impressions*$org->meta["bill"]["mic"];
            }
            $total = $click_cost + $imp_cost;
            $bill = new Bill([
                "org_id" => $org->id,
                "impressions" => $impressions,
                "clicks" => $clicks,
                "mic" => $org->meta["bill"]["mic"],
                "tcc" => $org->meta["bill"]["tcc"],
                "start" => $start,
                "end" => $end,
                "amount" => $total,
                "live" => false,
                "created" => Db::time('-7 day')
            ]);
            if ($total > 1) {
                $bill->save();
                $user = User::first(["org_id = ?" => $org->id, "type = ?" => "admin"]);
                Mail::send([
                    'user' => $user,
                    'bill' => $bill,
                    'template' => 'adminBilling',
                    'subject' => 'Billing at vNative',
                    'click_cost' => $click_cost,
                    'imp_cost' => $imp_cost,
                    'org' => $org
                ]);
            }
        }
    }

}
