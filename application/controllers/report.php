<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Report extends Admin {
    /**
     * @readwrite
     * @var string
     */
    protected $_start = null;
    
    /**
     * @readwrite
     * @var string
     */
    protected $_end = null;

    /**
     * @readwrite
     * @var string
     */
    protected $_limit = null;
    
    /**
     * @readwrite
     * @var string
     */
    protected $_page = null;

    public function __construct($opts = []) {
        parent::__construct($opts);
        $this->limit = (int) RM::get("limit", 10);
        $this->page = RM::get("page", 1);
        $this->start = RM::get("start", date('Y-m-d'));
        $this->end = RM::get("end", date('Y-m-d', strtotime('now')));
    }

    public function _displayData() {
        $q = ['start' => $this->start, 'end' => $this->end, 'limit' => $this->limit, 'page' => $this->page];
        $this->actionView->set($q);
    }
    
    /**
     * @before _secure
     * @after _displayData
     */
    public function campaigns() {
        $this->seo(array("title" => "Campaigns Effectiveness"));
        $view = $this->getActionView();

        // Only find the ads for this organizations
        $allAds = \Ad::all(['org_id' => $this->org->_id], ['_id']);
        $in = Db::convertType(array_keys($allAds));

        $match = [
            'created' => Db::dateQuery($this->start, $this->end),
            'is_bot' => false,
            'adid' => ['$in' => $in]
        ];
        $records = Db::collection('Click')->aggregate([
            ['$match' => $match],
            ['$project' => ['adid' => 1, '_id' => 0]],
            ['$group' => [
                '_id' => '$adid',
                'count' => ['$sum' => 1]
            ]],
            ['$sort' => ['count' => -1]],
            ['$limit' => (int) $this->limit]
        ]);
        $stats = [];
        foreach ($records as $r) {
            $arr = Utils::toArray($r);
            $id = Utils::getMongoID($arr['_id']);
            $stats[$id] = $arr['count'];
        }

        $view->set('stats', $stats);
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function ad($id) {
        $this->seo(array("title" => "AD Report"));
        $view = $this->getActionView();
        $ad = \Ad::first(['org_id = ?' => $this->org->_id, 'id = ?' => $id]);

        $count = Db::count([
            'created' => Db::dateQuery($this->start, $this->end),
            'adid' => $ad->_id,
            'is_bot' => false
        ]);
       
        $view->set('ad', $ad)
            ->set('clicks', $count);
    }

    /**
     * @before _secure
     * @after _displayData
     * @todo fetch realtime data for today (if start == end) But if start != today then fetch data from 
     * performance table and add it with realtime
     */
    public function publishers() {
        $this->seo(["title" => "Publisher Rankings"]); $view = $this->getActionView();

        $match = [
            'pid' => ['$in' => $this->org->users('publisher')],
            'is_bot' => false,
            'created' => Db::dateQuery($this->start, $this->end)
        ];

        $records = Db::collection('Click')->aggregate([
            ['$match' => $match],
            ['$project' => ['pid' => 1, 'device' => 1, '_id' => 0]],
            ['$group' => [
                '_id' => ['pid' => '$pid', 'device' => '$device'],
                'count' => ['$sum' => 1]
            ]],
            ['$sort' => ['count' => -1]],
            ['$limit' => (int) $this->limit]
        ]);

        $stats = $deviceStats = [];
        foreach ($records as $r) {
            $obj = Utils::toArray($r); $_id = $obj['_id'];
            $pid = Utils::getMongoID($_id['pid']);
            ArrayMethods::counter($stats, $pid, $obj['count']);
            
            if (!isset($deviceStats[$pid])) $deviceStats[$pid] = [];
            ArrayMethods::counter($deviceStats[$pid], $_id['device'], $obj['count']);

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
        }

        $view->set('stats', $stats)
            ->set('deviceStats', $deviceStats)
            ->set('bounceRate', $bounceRate ?? []);
    }

    protected function _searchQuery($fields, &$adIds, &$pIds) {
        $searchQ = RM::get('query', []);
        $query = $searching = [];
        foreach ($searchQ as $q) {
            if (!in_array($q, $fields)) continue;
            $searching[$q] = RM::get($q);

            switch ($q) {
                case 'adid':
                    $adIdsGet = RM::get($q, []);
                    if (ArrayMethods::inArray($adIds, $adIdsGet)) {
                        $adIds = $adIdsGet;
                    }
                    break;

                case '_id':
                case 'cid':
                    $id = RM::get($q, null);
                    if ($id) {
                        $query[$q] = Db::convertType($id);
                    }
                    break;

                case 'is_bot':
                    $is_bot = RM::get($q, null);
                    if (!is_null($is_bot)) {
                        $query[$q] = (boolean) ((int) $is_bot);
                    }
                    break;

                case 'pid':
                    $pIdsGet = RM::get($q, []);
                    if (ArrayMethods::inArray($pIds, $pIdsGet)) {
                        $pIds = $pIdsGet;
                    }
                    break;
                
                case 'device':
                case 'os':
                case 'country':
                    $reqData = RM::get($q, []);
                    if (count($reqData) === 0) break;
                    $data = implode("|", $reqData);

                    $query[$q] = Db::convertType($data, 'regex');
                    break;

                case 'referer':
                case 'p1':
                case 'p2':
                case 'cookie':
                    $data = RM::get($q);
                    if ($data) {
                        $query[$q] = Db::convertType($data, 'regex');   
                    }
                    break;

                case 'ip':
                    $reqData = RM::get('ip', '');
                    $pieces = explode(",", $reqData);
                    if (count($pieces) === 0) break;
                    $data = implode("|", $pieces);

                    $query[$q] = Db::convertType($data, 'regex');
                    break;
            }
        }
        return [
            'query' => $query, 'searching' => $searching
        ];
    }

    protected function _reports($table) {
        $view = $this->getActionView();
        $fields = Shared\Services\User::fields($table);
        $table = "\\{$table}";

        $ads = \Ad::all(['org_id' => $this->org->_id], ['_id', 'title'], 'created', 'desc');
        $affs = \User::all(['org_id' => $this->org->_id, 'type = ?' => 'publisher'], ['_id', 'name']);
        $adIds = array_keys($ads); $pIds = array_keys($affs);

        $searchData = $this->_searchQuery($fields, $adIds, $pIds);
        $query = $searchData['query']; $searching = $searchData['searching'];

        $adIds = Db::convertType($adIds); $pIds = Db::convertType($pIds);
        $query['adid'] = ['$in' => $adIds];
        $query['pid'] = ['$in' => $pIds];
        $query['created'] = Db::dateQuery($this->start, $this->end);

        $records = $table::all($query, [], 'created', 'desc', $this->limit, $this->page);
        $count = $table::count($query);

        $view->set([
            'clicks' => $records, 'fields' => $fields,
            'count' => $count, 'ads' => $ads,
            'query' => $searching, 'affs' => $affs
        ]);
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function clicks() {
        $this->seo(["title" => "Click Logs"]);
        $this->_reports("Click");
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function conversions() {
        $this->seo(["title" => "Conversions"]);
        $this->_reports("Conversion");
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function impressions() {
        $this->seo(["title" => "Impressions"]);
        $this->_reports("Impression");
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function links() {
        $this->seo(array("title" => "Link Logs"));
        $view = $this->getActionView();

        $prop = RM::get("property"); $val = RM::get("value");
        $sort = RM::get("sort", "desc"); $sign = RM::get("sign", "equal");
        $orderBy = RM::get("order", 'created');
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
        $dateQuery = Utils::dateQuery(['start' => $this->start, 'end' => $this->end]);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $records = \Link::all($query, [], $orderBy, $sort, $this->limit, $this->page);
        $count = \Link::count($query);

        $view->set([
            'links' => $records, 'fields' => $fields,
            'property' => $prop, 'value' => $val,
            'sign' => $sign, 'sort' => $sort,
            'order' => $orderBy, 'count' => $count,
            'query' => $searching
        ]);
    }

    /**
     * @before _secure
     * @after _displayData
     */
    public function platforms($id = null) {
        $this->seo(["title" => "Platform wise click stats"]);
        $view = $this->getActionView(); $org = $this->org;

        $clickCol = Registry::get("MongoDB")->clicks;

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
        $query['created'] = Db::dateQuery($this->start, $this->end);

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
}
