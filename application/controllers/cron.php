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
        $this->widgets();
    }

    protected function _weekly() {
        // implement
    }

    protected function _monthly() {
        // implement
    }

    protected function _daily() {
        $this->log("CRON Started");

        $this->_pubPerf();
        $this->_advertPerf();
        $this->_webPerf();
        $this->_rssFeed();
        // $this->_test();
    }

    protected function _test() {
        $start = date('Y-m-d', strtotime('-3 day'));
        $end = date('Y-m-d', strtotime('-3 day'));

        $diff = date_diff(date_create($start), date_create($end));
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($start . " +{$i} day"));
            var_dump($date);

            // $this->_pubPerf($date);
            // $this->_advertPerf($date);
            // $this->_webPerf($date);
        }
    }

    public function widgets() {
        $this->log("Widgets Started");
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $clickCol = Registry::get("MongoDB")->clicks;

        $orgs = Organization::all(["live = ?" => true]);
        foreach ($orgs as $org) {
            if(!array_key_exists("widgets", $org->meta)) continue;
            $result = ['publishers' => [], 'ads' => []]; $in = []; $pubClicks = [];
            $pubs = User::all(["org_id = ?" => $org->_id, "type = ?" => "publisher"], ["_id", "name"]);
            foreach ($pubs as $pb) {
                $in[] = $pb->_id;
            }
            
            $records = $clickCol->find([
                "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']],
                "is_bot" => false,
                "pid" => ['$in' => $in]
            ], ['adid', 'pid']);

            $uniqClicks = []; $adClicks = []; $pubClicks = [];
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

            $meta = $org->meta;
            $meta["widget"] = [];
            // sort publishers based on clicks and find their details
            if (in_array("top10pubs", $meta["widgets"])) {
                arsort($pubClicks); array_splice($pubClicks, 10);
                foreach ($pubClicks as $pid => $count) {
                    $u = $pubs[$pid];
                    $result['publishers'][] = [
                        "_id" => $pid,
                        "name" => $u->name,
                        "count" => $count
                    ];
                }
                $meta["widget"]["top10pubs"] = $result['publishers'];
            }

            if (in_array("top10ads", $meta["widgets"])) {
                arsort($adClicks); array_splice($adClicks, 10);
                foreach ($adClicks as $adid => $count) {
                    $result['ads'][] = [
                        '_id' => $adid,
                        'clicks' => $count
                    ];
                }
                $meta["widget"]["top10ads"] = $result['ads'];
            }
            $org->meta = $meta;
            $this->log("Widget Saved for Org: " . $org->name);
            $org->save();
        }
        $this->log("Widgets Done");
    }

    protected function _pubPerf($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }
        // find the publishers
        $publishers = \User::all(['type = ?' => 'publisher', 'live = ?' => true], ['_id', 'email', 'meta', 'org_id']);

        // store AD commission info
        $adsInfo = []; $orgs = [];
        foreach ($publishers as $p) {
            // @todo find a way to cope up with these multiple array_key_exists statements
            $org_id = Utils::getMongoID($p->org_id);
            if (!array_key_exists($org_id, $orgs)) {
                $org = \Organization::first(['_id' => $org_id], ['url']);
                $orgs[$org_id] = $org;
            } else {
                $org = $orgs[$org_id];
            }
            // find the clicks for the publisher
            $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
            $start = $dateQuery['start']; $end = $dateQuery['end'];
            
            $clickCol = Registry::get("MongoDB")->clicks;
            $clicks = $clickCol->find([
                'pid' => $p->_id,
                'is_bot' => false,
                'created' => ['$gte' => $start, '$lte' => $end]
            ], ['adid']);

            $perf = Performance::exists($p, $date);
            
            // classify the clicks according to AD ID
            $classify = \Click::classify($clicks, 'adid');
            foreach ($classify as $key => $value) {
                $adClicks = count($value); $conversions = 0;

                $info = \Commission::campaignRate($key, $adsInfo, $org, [
                    'type' => 'publisher', 'dateQuery' => $dateQuery, 'publisher' => $p
                ]);
                $adsInfo = $info['adsInfo']; $rate = $info['rate'];

                if ($info['conversions'] !== false) {    // not a CPC campaign
                    $conversions = $info['conversions'];
                    $revenue = $conversions * $rate;
                } else {
                    $revenue = $rate * $adClicks;    
                }
                
                $perf->clicks += $adClicks; $perf->conversions += $conversions;
                $perf->revenue += round($revenue, 6);
                $perf->impressions += \Impression::getStats($key, $p->_id, $dateQuery);
            }

            if ($perf->clicks == 0) {
                if ($perf->impressions == 0) {
                    continue;
                } else {
                    $avgCpc = "0.00";
                }
            } else {
                $avgCpc = $perf->revenue / $perf->clicks;
            }
            $perf->cpc = round($avgCpc, 6);

            $msg = 'Performance saved for user: ' . $p->email. ' with clicks: ' . $perf->clicks . ' impressions: ' . $perf->impressions;
            $this->log($msg);
            $perf->save();
        }
    }

    protected function _advertPerf($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }
        $users = \User::all(['type' => 'advertiser', 'live' => true], ['_id', 'meta', 'email', 'org_id']);

        $adsInfo = [];  $orgs = [];
        foreach ($users as $u) {
            $org_id = Utils::getMongoID($u->org_id);
            if (!array_key_exists($org_id, $orgs)) {
                $org = \Organization::first(['_id' => $org_id], ['url', 'meta']);
                $orgs[$org_id] = $org;
            } else {
                $org = $orgs[$org_id];
            }
            $perf = Performance::exists($u, $date);

            // find ads for the publisher
            $clickCol = Registry::get("MongoDB")->clicks;
            $ads = \Ad::all(['user_id' => $u->_id], ['_id', 'title']);
            $clicksCount = 0; $revenue = 0.00;
            $imp = 0; $conversions = 0;
            foreach ($ads as $a) {
                // find clicks for the ad for the given date
                $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
                $start = $dateQuery['start']; $end = $dateQuery['end'];

                $clicks = $clickCol->count([
                    'adid' => $a->_id,
                    'is_bot' => false,
                    'created' => ['$gte' => $start, '$lte' => $end]
                ]);
                $adid = Utils::getMongoID($a->_id);
                $info = \Commission::campaignRate($adid, $adsInfo, $org, ['type' => 'advertiser', 'dateQuery' => $dateQuery, 'advertiser' => $u]);
                $orate = $info['rate']; $adsInfo = $info['adsInfo'];

                $conv = $info['conversions'];
                if ($conv !== false) {    // not a CPC campaign
                    $conversions += $conv;
                    $revenue += $conv * $orate;
                } else {
                    $revenue += $clicks * $orate;
                }

                $clicksCount += $clicks; 
                $imp += \Impression::getStats($a->_id, null, $dateQuery);
            }
            $perf->clicks = $clicksCount; $perf->revenue = round($revenue, 6);
            $perf->impressions = $imp; $perf->conversions = $conversions;
            if ($perf->clicks == 0) {
                if ($perf->impressions === 0) {
                    continue;
                } else {
                    $avgCpc = "0.00";
                }
            } else {
                $avgCpc = abs($perf->revenue) / $perf->clicks;
            }

            $perf->cpc = round($avgCpc, 6);
            $msg = 'Saving performance for advertiser: ' . $u->email . ' with clicks: ' . $perf->clicks . ' earning: '. $perf->revenue .' impressions: ' . $perf->impressions;
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
                    $in[] = $a->_id;
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
                ], ['adid']);

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
                $meta = $p->meta;
                $rss = $meta['rss'];

                if (!$rss['parsing']) {
                    continue;   // parsing is stopeed
                }
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
                \Meta::campImport($user->_id, $p->user_id, $result['urls']);
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
            $uid = Utils::getMongoID($m->propid);
            // find user info
            if (!array_key_exists($uid, $users)) {
                $user = \User::first(['_id = ?' => $m->propid], ['_id', 'org_id', 'email', 'meta']);
                $users[$uid] = $user;
            } else {
                $user = $users[$uid];
            }

            // find organization
            $orgid = Utils::getMongoID($user->org_id);
            if (!array_key_exists($orgid, $orgs)) {
                $org = \Organization::first(['_id = ?' => $user->org_id], ['meta', 'domain', '_id']);
                $orgs[$orgid] = $org;
            } else {
                $org = $orgs[$orgid];
            }

            $categories = \Category::all(['org_id' => $org->_id], ['_id', 'name']);
            $categories = array_values($categories);
            $index = array_rand($categories);
            $category = $categories[$index];

            // fetch ad info foreach URL
            $advert_id = $m->value['advert_id'];
            $comm = $org->meta;
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
                    $commission = new \Commission([
                        'ad_id' => $ad->_id,
                        'model' => $comm['model'],
                        'rate' => $comm['rate'],
                        'revenue' => @$user->meta['rate'],
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

}
