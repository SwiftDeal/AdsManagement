<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RM;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Shared\Utils as Utils;
use Shared\Services\Db as Db;

class Report extends Admin {
    
    /**
     * @before _secure
     */
    public function ads() {
        $this->seo(array("title" => "ADS Effectiveness"));
        $view = $this->getActionView();

        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $q = ['start' => $start, 'end' => $end]; $view->set($q);

        // Only find the ads for this organizations
        $allAds = \Ad::all(['org_id' => $this->org->_id], ['_id']);
        $in = Utils::mongoObjectId(array_keys($allAds));

        $clicks = Db::query('Click', [
            'created' => Db::dateQuery($start, $end),
            'is_bot' => false,
            'adid' => ['$in' => $in]
        ], ['adid']);

        $classify = \Click::classify($clicks, 'adid');
        $stats = Click::counter($classify);
        $stats = ArrayMethods::topValues($stats, 50);
        $view->set('stats', $stats);
    }

    /**
     * @before _secure
     */
    public function ad($id) {
        $this->seo(array("title" => "AD Report"));
        $view = $this->getActionView();

        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-5 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $q = ['start' => $start, 'end' => $end]; $view->set($q);
        $ad = \Ad::first(['org_id = ?' => $this->org->_id, 'id = ?' => $id]);

        $count = Db::count([
            'created' => Db::dateQuery($start, $end),
            'adid' => $ad->_id,
            'is_bot' => false
        ]);
       
        $view->set('ad', $ad)
            ->set('clicks', $count);
    }

    /**
     * @before _secure
     */
    public function publishers() {
        $this->seo(array("title" => "Publisher Rankings"));
        $view = $this->getActionView();

        $start = RM::get("start", date('Y-m-d'));
        $end = RM::get("end", date('Y-m-d'));
        $q = ['start' => $start, 'end' => $end]; $view->set($q);
        $dateQuery = Utils::dateQuery($q);
        $start = $dateQuery['start']; $end = $dateQuery['end'];

        $clicks = Db::query('Click', [
            'pid' => ['$in' => $this->org->users('publisher')],
            'is_bot' => false,
            'created' => Db::dateQuery($start, $end)
        ], ['pid', 'device', 'adid']);
        
        $classify = \Click::classify($clicks, 'pid');
        $stats = Click::counter($classify);
        $stats = ArrayMethods::topValues($stats, 50);

        $deviceStats = []; $bounceRate = [];
        foreach ($stats as $pid => $count) {
            $pubClicks = $classify[$pid];
            // taking a lot of time @todo (do something about it)
            /*$adClicks = Click::classify($pubClicks, 'adid');

            $br = 0; $total = count($adClicks);
            foreach ($adClicks as $adid => $records) {
                $pageViews = Db::query('PageView', ['pid' => $pid, 'adid' => $adid], ['cookie']);
                // Create a counter based on cookie and select only the values whose 
                // counter is less than 2
                $multiPageSessions = 0; $totalClicks = count($records);
                $pageViews = Click::classify($pageViews, 'cookie');
                foreach ($pageViews as $ckid => $rows) {
                    if (count($rows) >= 2) {
                        $multiPageSessions++;
                    }
                }
                $bounce = 1 - ($multiPageSessions / $totalClicks);
                $br += $bounce;
            }
            $bounceRate[$pid] = (int) (round($br / $total, 2) * 100);*/

            $device = Click::classifyInfo(['clicks' => $pubClicks, 'type' => 'device', 'arr' => []]);
            $deviceStats[$pid] = $device;
        }

        $view->set('stats', $stats)
            ->set('deviceStats', $deviceStats)
            ->set('bounceRate', $bounceRate);
    }

    /**
     * @before _secure
     */
    public function clicks() {
        $this->seo(array("title" => "Click Logs"));
        $view = $this->getActionView();

        $limit = RM::get("limit", 10); $page = RM::get("page", 1);
        $prop = RM::get("property"); $val = RM::get("value");
        $sort = RM::get("sort", "desc"); $sign = RM::get("sign", "equal");
        $orderBy = RM::get("order", 'created');
        $start = RM::get("start", date('Y-m-d', strtotime('-7 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));
        $fields = (new \Click())->getColumns();

        // Only find the ads for this organizations
        $ads = \Ad::all(['org_id' => $this->org->_id], ['_id']);
        $query = ['adid' => [
            '$in' => Utils::mongoObjectId(array_keys($ads))
            ]
        ];

        $searching = [];
        foreach ($fields as $key => $value) {
            $search = RM::get($key);
            if (!$search) continue;
            $searching[$key] = $search;

             // Only allow full object ID's and rest regex searching
            if (in_array($key, ['pid', 'adid', '_id'])) {
                $query[$key] = RM::get($key);
            } else {
                $query[$key] = Utils::mongoRegex($search);
            }
        }
        $query['created'] = Db::dateQuery($start, $end);

        $records = \Click::all($query, [], $orderBy, $sort, $limit, $page);
        $count = \Click::count($query);

        $view->set([
            'clicks' => $records, 'fields' => $fields,
            'limit' => $limit, 'page' => $page,
            'property' => $prop, 'value' => $val,
            'sign' => $sign, 'sort' => $sort,
            'order' => $orderBy, 'count' => $count,
            'start' => $start, 'end' => $end, 'query' => $searching
        ]);
    }

    /**
     * @before _secure
     */
    public function links() {
        $this->seo(array("title" => "Link Logs"));
        $view = $this->getActionView();

        $limit = RM::get("limit", 10); $page = RM::get("page", 1);
        $prop = RM::get("property"); $val = RM::get("value");
        $sort = RM::get("sort", "desc"); $sign = RM::get("sign", "equal");
        $orderBy = RM::get("order", 'created');
        $start = RM::get("start", date('Y-m-d', strtotime('-7 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));
        $fields = (new \Link())->getColumns();

        $searching = $query = [];
        $query['user_id'] = ['$in' => $this->org->users('publisher')];
        foreach ($fields as $key => $value) {
            $search = RM::get($key);
            if (!$search) continue;
            $searching[$key] = $search;

             // Only allow full object ID's and rest regex searching
            if (in_array($key, ['user_id', 'ad_id', '_id'])) {
                $query[$key] = RM::get($key);
            } else {
                $query[$key] = Utils::mongoRegex($search);
            }
        }
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $records = \Link::all($query, [], $orderBy, $sort, $limit, $page);
        $count = \Link::count($query);

        $view->set([
            'links' => $records, 'fields' => $fields,
            'limit' => $limit, 'page' => $page,
            'property' => $prop, 'value' => $val,
            'sign' => $sign, 'sort' => $sort,
            'order' => $orderBy, 'count' => $count,
            'start' => $start, 'end' => $end, 'query' => $searching
        ]);
    }

    /**
     * @before _secure
     */
    public function platforms($id = null) {
        $this->seo(["title" => "Platform wise click stats"]);
        $view = $this->getActionView(); $org = $this->org;

        $clickCol = Registry::get("MongoDB")->clicks;
        $start = RM::get("start", date('Y-m-d', strtotime('-1 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));
        $view->set(['start' => $start, 'end' => $end]);

        // find the platforms
        $platforms = \Platform::all([
            'user_id' => ['$in' => $org->users('advertisers')]
        ], ['_id', 'url']);
        if (count($platforms) === 0) {
            return $view->set(['platforms' => [], 'publishers' => []]);
        }

        $key = array_rand($platforms);
        $url = RM::get('link', $platforms[$key]->url);
        
        // find ads having this url
        $ads = \Ad::all(['org_id' => $org->_id], ['_id', 'url']);
        $in = Utils::mongoObjectId(array_keys($ads)); $matched = [];
        foreach ($ads as $a) {
            $regex = preg_quote($url, '.');
            if (preg_match('#^'.$regex.'#', $a->url)) {
                $matched[] = Utils::mongoObjectId($a->_id);
            }
        }

        if (count($matched) === 0) {
            $query['adid'] = ['$in' => $in];
        } else {
            $query['adid'] = ['$in' => $matched];
        }

        $query['is_bot'] = false;
        $query['created'] = Db::dateQuery($start, $end);

        $records = $clickCol->aggregate([
            ['$match' => $query],
            ['$projection' => ['_id' => 1, 'pid' => 1]],
            ['$group' => ['_id' => '$pid', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
        ]);

        $result = []; $publishers = [];
        foreach ($records as $r) {
            $obj = (object) $r; $id = Utils::getMongoID($obj->_id);
            $user = User::first(['_id' => $id], ['_id', 'name']);
            
            $result[$id] = (object) [
                '_id' => $user->_id,
                'name' => $user->name,
                'clicks' => $obj->count
            ];
        }

        $view->set([
            'platforms' => $platforms, 'link' => $url,
            'publishers' => $result
        ]);
    }

    /**
     * @before _secure
     */
    public function conversions() {
        $this->seo(array("title" => "Conversions"));
        $view = $this->getActionView();

        if (RequestMethods::get("action") == "conversions") {
            $convertCol = Registry::get("MongoDB")->clicks;
            $start = RM::get("start", date('Y-m-d', strtotime('-7 day')));
            $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));

            $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
            $query['is_bot'] = false;
            $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

            $records = $convertCol->find($query, ['projection' => ['adid' => 1, 'pid' => 1]]);
        }
    }

    /**
     * @before _secure
     */
    public function installs($campaign_id) {
        $this->seo(array("title" => "Click Logs"));
        $view = $this->getActionView();
    }

    /**
     * @before _secure
     */
    public function impressions($campaign_id) {
        $this->seo(array("title" => "Click Logs"));
        $view = $this->getActionView();
    }
}
