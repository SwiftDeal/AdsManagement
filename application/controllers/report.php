<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RM;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Shared\Utils as Utils;

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
        $dateQuery = Shared\Utils::dateQuery($q);
        $start = $dateQuery['start']; $end = $dateQuery['end'];

        // Only find the ads for this organizations
        $allAds = \Ad::all(['org_id' => $this->org->_id], ['_id', 'title', 'url', 'image', 'live']);
        $in = []; $ads = [];
        foreach ($allAds as $a) {
            $in[] = Utils::mongoObjectId($a->_id);
        }

        $clickCol = Registry::get("MongoDB")->clicks;
        $clicks = $clickCol->find([
            'created' => ['$gte' => $start, '$lte' => $end],
            'is_bot' => false,
            'adid' => ['$in' => $in]
        ], ['adid']);

        $classify = \Click::classify($clicks, 'adid');
        foreach ($classify as $key => $value) {
            $ads[$key] = count($value);
        }
        
        if (count($ads) == 0) return $view->set('ads', []);
        arsort($ads, SORT_NUMERIC);
        $result = [];
        foreach ($ads as $key => $value) {
        	$a = $allAds[$key];
        	$result[] = ArrayMethods::toObject([
        		'title' => $a->title,
                'id' => $key,
        		'image' => CDN. 'uploads/images/'.$a->image,
        		'live' => $a->live,
                'url' => $a->url,
        		'clicks' => $value
        	]);
        }
        $view->set('ads', $result);
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
        $dateQuery = Shared\Utils::dateQuery($q);
        $start = $dateQuery['start']; $end = $dateQuery['end'];

        $ad = \Ad::first(['org_id = ?' => $this->org->_id, 'id = ?' => $id]);

        $clickCol = Registry::get("MongoDB")->clicks; $ads = [];
        $count = $clickCol->count([
            'created' => ['$gte' => $start, '$lte' => $end],
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

        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $q = ['start' => $start, 'end' => $end]; $view->set($q);
        $dateQuery = Utils::dateQuery($q);
        $start = $dateQuery['start']; $end = $dateQuery['end'];

        // find all the publishers for this organization
        $users = \User::all(['type' => 'publisher', 'org_id' => $this->org->_id], ['_id', 'name']);
        $in = [];
        foreach ($users as $u) {
            $in[] = Utils::mongoObjectId($u->_id);
        }

        $clickCol = Registry::get("MongoDB")->clicks; $stats = [];
        $clicks = $clickCol->find([
            'created' => ['$gte' => $start, '$lte' => $end],
            'is_bot' => false,
            'pid' => ['$in' => $in]
        ], ['pid']);
        
        $classify = \Click::classify($clicks, 'pid');
        foreach ($classify as $key => $value) {
            $stats[$key] = count($value);
        }

        if (count($stats) == 0) return $view->set('publishers', []);
        arsort($stats, SORT_NUMERIC);
        $result = [];
        foreach ($stats as $key => $value) {
            $u = $users[$key];

        	$result[] = ArrayMethods::toObject([
        		'name' => $u->name,
        		'clicks' => $value
			]);
        }

        $view->set('publishers', $result);
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
        $in = []; $query = [];
        foreach ($ads as $a) {
            $in[] = Utils::mongoObjectId($a->_id);
        }

        $query['adid'] = ['$in' => $in]; $searching = [];
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
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

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

        // Only find the users for this organizations
        $users = \User::all(['org_id' => $this->org->_id], ['_id']);
        $in = []; $query = [];
        foreach ($users as $u) {
            $in[] = Utils::mongoObjectId($u->_id);
        }

        $query['user_id'] = ['$in' => $in]; $searching = [];
        foreach ($fields as $key => $value) {
            $search = RM::get($key);
            if (!$search) continue;
            $searching[$key] = $search;

             // Only allow full object ID's and rest regex searching
            if (in_array($key, ['user_id', 'ad_id', '_id'])) {
                $query[$key] = RM::get($key);
            } else {
                $query[$key] = new \MongoDB\BSON\Regex("/$search/i");
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
    public function platforms() {
        $this->seo(["title" => "Platform wise click stats"]);
        $view = $this->getActionView();

        $clickCol = Registry::get("MongoDB")->clicks;
        $start = RM::get("start", date('Y-m-d', strtotime('-1 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));

        // find all the users
        $users = \User::all(['org_id' => $this->org->_id, 'type' => 'publisher'], ['_id', 'name']);
        $advertisers = \User::all(['org_id' => $this->org->_id, 'type' => 'advertiser'], ['_id', 'email']);
        $in = []; $url = null;
        foreach ($advertisers as $a) {
            $in[] = $a->_id;
        }

        // find the platforms
        $platforms = \Platform::all(['user_id' => ['$in' => $in]], ['_id', 'url']);

        if (count($platforms) > 0) {
            $key = array_rand($platforms);
            $url = RM::get('link', $platforms[$key]->url); $in = [];
            
            // find ads having this url
            $ads = \Ad::all(['org_id' => $this->org->_id], ['_id', 'url']);
            foreach ($ads as $a) {
                $regex = preg_quote($url, '.');
                if (preg_match('#^'.$regex.'#', $a->url)) {
                    $in[] = Utils::mongoObjectId($a->_id);
                }
            }

            $query['adid'] = ['$in' => $in];
        }

        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query['is_bot'] = false;
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $records = $clickCol->find($query, ['adid', 'pid']);
        $count = \Click::count($query);

        $stats = []; $count = 0;
        $clicks = \Click::classify($records, 'adid');
        foreach ($clicks as $key => $value) {
            $pubClicks = Click::classify($value, 'pid');
            foreach ($pubClicks as $k => $v) {
                // $uniqClicks = Click::checkFraud($v);
                $uniqCount = count($v);
                $count += $uniqCount;

                if (!array_key_exists($k, $stats)) {
                    $stats[$k] = $uniqCount;
                } else {
                    $stats[$k] += $uniqCount;
                }
            }
        }

        arsort($stats); $result = [];
        foreach ($stats as $k => $value) {
            $result[$k] = ArrayMethods::toObject([
                'name' => $users[$k]->name,
                'clicks' => $value
            ]);
        }

        $view->set([
            'platforms' => $platforms, 'publishers' => $result,
            'link' => $url, 'count' => $count,
            'start' => $start, 'end' => $end
        ]);
    }
}
