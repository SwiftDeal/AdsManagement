<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;
use Framework\Registry as Registry;

class Cron extends Shared\Controller {

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
       $this->importCampaigns();
    }

    protected function _daily() {
        $this->log("CRON Started");

        $this->_pubPerf();
        $this->_advertPerf();
        $this->_webPerf();
        // $this->_test();
    }

    protected function _test() {
        $start = date('Y-m-d', strtotime('-2 day'));
        $end = date('Y-m-d', strtotime('-1 day'));

        $diff = date_diff(date_create($start), date_create($end));
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($start . " +{$i} day"));
            
            // $this->_pubPerf($date);
            // $this->_advertPerf($date);
            // $this->_webPerf($date);
        }
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
                'created' => ['$gte' => $start, '$lte' => $end]
            ],['adid', 'cookie', 'ipaddr', 'referer']);

            $perf = Performance::exists($p, $date);
            
            // classify the clicks according to AD ID
            $classify = \Click::classify($clicks, 'adid');
            foreach ($classify as $key => $value) {
                if (!array_key_exists($key, $adsInfo)) {
                    $comm = \Commission::first(['ad_id = ?' => $key], ['model', 'rate']);
                    $adsInfo[$key] = $comm;
                } else {
                    $comm = $adsInfo[$key];
                }

                // Check for click fraud
                $uniqClicks = Click::checkFraud($value, $org);
                $adClicks = count($uniqClicks);

                if (isset($p->meta['campaign']) && !is_null($p->meta['campaign']['rate'])) {
                    $rate = $p->meta['campaign']['rate'];
                } else {
                    $rate = $comm->rate;
                }
                $revenue = ((float) $rate) * $adClicks;

                $perf->clicks += $adClicks;
                $perf->revenue += round($revenue, 6);
            }

            if ($perf->clicks == 0) {
                continue;
            } else {
                $avgCpc = $perf->revenue / $perf->clicks;
            }
            $perf->cpc = round($avgCpc, 6);

            $msg = 'Performance saved for user: ' . $p->email. ' with clicks: ' . $perf->clicks;
            $this->log($msg);
            $perf->save();
        }
    }

    protected function _advertPerf($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }
        $users = \User::all(['type' => 'advertiser', 'live' => true], ['_id', 'email', 'org_id']);

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
            foreach ($ads as $a) {
                // find clicks for the ad for the given date
                $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
                $start = $dateQuery['start']; $end = $dateQuery['end'];

                $records = $clickCol->find([
                    'adid' => $a->_id,
                    'created' => ['$gte' => $start, '$lte' => $end]
                ], ['adid', 'ipaddr', 'cookie', 'referer']);
                $records = ArrayMethods::toObject($records);
                $arr = Click::checkFraud($records, $org);
                $clicks = count($arr);

                $key = Utils::getMongoID($a->_id);

                if (!array_key_exists($key, $adsInfo)) {
                    $comm = \Commission::first(['ad_id' => $key], ['bid']);
                    $adsInfo[$key] = $comm;
                } else {
                    $comm = $adsInfo[$key];
                }
                if (is_null($comm->bid) || !$comm->bid) {
                    $orate = isset($org->meta['rate']) ? $org->meta['rate'] : 0;
                } else {
                    $orate = $comm->bid;
                }

                $clicksCount += $clicks;
                $revenue += $clicks * $orate;
            }
            $perf->clicks = $clicksCount; $perf->revenue = round($revenue, 6);
            if ($perf->clicks == 0) {
                continue;
            }

            $avgCpc = abs($perf->revenue) / $perf->clicks;
            $perf->cpc = round($avgCpc, 6);
            $msg = 'Saving performance for advertiser: ' . $u->email . ' with clicks: ' . $perf->clicks;
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
                $stat = new \Stat([
                    'pid' => $p->_id, 'impressions' => null,
                    'created' => $date
                ]);
                $clicks = 0; $revenue = 0.00; $avgCpc = 0.00;

                $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
                $start = $dateQuery['start']; $end = $dateQuery['end'];

                // find all the clicks for the date range
                $records = $clickCol->find([
                    'adid' => ['$in' => $in],
                    'created' => ['$gte' => $start, '$lte' => $end]
                ], ['ipaddr', 'cookie', 'referer', 'device', 'adid']);

                // Now classify the clicks according to the AD
                $classify = \Click::classify($records);

                // foreach ad remove fraud clicks
                foreach ($classify as $key => $value) {
                    if (!array_key_exists($key, $adsInfo)) {
                        $comm = \Commission::first(['ad_id' => $key], ['bid']);
                        $adsInfo[$key] = $comm;
                    } else {
                        $comm = $adsInfo[$key];
                    }

                    $uniqClicks = Click::checkFraud($value, $org);
                    // can parse uniqClicks to find traffic from individual devices
                    $adClicks = count($uniqClicks);
                    
                    $clicks += $adClicks;
                    $revenue += $adClicks * $comm->bid;
                }

                if ($clicks == 0) {
                    continue;
                }
                $avgCpc = round($revenue / $clicks, 6);
                $stat->clicks = $clicks; $stat->revenue = round($revenue, 6);
                $stat->cpc = $avgCpc;

                $msg = 'Stats for platform: ' .$p->url . ' Clicks: ' . $stat->clicks;
                $this->log($msg);
                $stat->save();
            }
        }
    }

    protected function _weekly() {
        // implement
    }

    protected function _monthly() {
        // implement
    }

    protected function importCampaigns() {
        $metas = \Meta::all(['prop = ?' => 'campImport']);

        $users = []; $orgs = [];
        foreach ($metas as $m) {
            $uid = $m->getMongoID($m->propid);
            // find user info
            if (!array_key_exists($uid, $users)) {
                $user = \User::first(['_id = ?' => $m->propid], ['_id', 'org_id', 'email', 'meta']);
                $users[$uid] = $user;
            } else {
                $user = $users[$uid];
            }

            // find organization
            $orgid = $user->getMongoID($user->org_id);
            if (!array_key_exists($orgid, $orgs)) {
                $org = \Organization::first(['_id = ?' => $user->org_id], ['meta', 'domain', '_id']);
                $orgs[$orgid] = $org;
            } else {
                $org = $orgs[$orgid];
            }

            // fetch ad info foreach URL
            $advert_id = $m->value['advert'];
            $comm = $org->meta;
            $urls = $m->value['urls'];

            foreach ($urls as $url) {
                $ad = \Ad::first(['user_id' => $advert_id, 'org_id' => $org->_id, 'url' => $url]);
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
                    'category' => ['386']
                ]);
                $ad->save();
                $commission = new \Commission([
                    'ad_id' => $ad->_id,
                    'model' => $comm['model'],
                    'rate' => $comm['rate'],
                    'bid' => $user->meta['rate'],
                    'country' => 'ALL'
                ]);
                $commission->save();
            }
            $this->log('Campaigns inported for the user: ' . $user->email);
            $m->delete();
        }
    }

}
