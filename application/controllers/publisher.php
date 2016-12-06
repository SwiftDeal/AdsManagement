<?php
/**
 * Controller to manage publisher actions
 *
 * @author Faizan Ayubi
 */
use Shared\{Utils, Mail};
use Shared\Services\Db as Db;
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Publisher extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView(); $commissions = []; $clicks = 0;$d = 1;
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime("-1 day"));
        $perf = Shared\Services\User::livePerf($this->user, $today, $today);
        $yestPerf = Shared\Services\User::livePerf($this->user, $yesterday, $yesterday);
        
        $total = Performance::total(
            [
                'start' => date("Y-m-d", strtotime('-365 day')),
                'end' => date("Y-m-d", strtotime('-2 day'))
            ],
            $this->user
        );
        $notifications = Notification::all([
            "org_id = ?" => $this->org->id,
            "meta = ?" => ['$in' => ['all', $this->user->_id]]
        ], [], "created", "desc", 5, 1);
        $topusers = $this->widgets();
        $view->set("topusers", $topusers)
            ->set("notifications", $notifications)
            ->set("total", $total)
            ->set("performance", $perf)
            ->set("tdomains", \Shared\Services\User::trackingLinks($this->user, $this->org))
            ->set("yestPerf", $yestPerf);
    }
    /**
     * @before _secure
     */
    public function campaigns() {
    	$this->seo(array("title" => "Campaigns"));$view = $this->getActionView();

        $limit = RM::get("limit", 20); $page = RM::get("page", 1);
        $category = RM::get("category", []); $keyword = RM::get("keyword", '');
        $query = ["live = ?" => true, "org_id = ?" => $this->org->_id];
        $query["meta.private"] = ['$ne' => true]; $today = date('Y-m-d');

        if (count($category) > 0) {
            // if you want AND query instead of OR query then replace '$in' --> '$all'
            $query["category"] = ['$in' => Ad::setCategories($category)];
        }
        if ($keyword) {
            $query["title"] = Utils::mongoRegex($keyword);
        }
        switch (RM::get("action")) {
            case 'trending':
                $ads = \Ad::all($query, [], 'modified', 'desc');
                $fields = Shared\Services\User::fields('Ad');
                $ids = array_keys($ads); $ads = Ad::objectArr($ads, $fields);
                $limit = $count = 30;

                $clickCol = Registry::get("MongoDB")->clicks;
                $results = $clickCol->aggregate([
                    ['$match' => [
                        'adid' => ['$in' => Db::convertType($ids, 'id')],
                        'is_bot' => false,
                        'created' => Db::dateQuery(RM::get("start", $today), RM::get("end", $today)),
                    ]],
                    ['$project' => ['adid' => 1, '_id' => 1]],
                    ['$group' => ['_id' => '$adid', 'count' => ['$sum' => 1]]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => $limit]
                ]);

                $ans = []; 
                foreach ($results as $r) {
                    $a = (object) $r; $key = Utils::getMongoID($a->_id);
                    $ans[$key] = $ads[$key];
                }
                $ads = $ans;
                break;
            
            default:
                $count = \Ad::count($query);
                $ads = \Ad::all($query, [], 'modified', 'desc', $limit, $page);
                break;
        }
    	
        //private campaigns
        $query["meta.private"] = true;
        $query["meta.access"] = ['$in' => [$this->user->id]];
        $pads = \Ad::all($query, [], 'modified', 'desc');
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);
        $user = $this->user; $model = null; $rate = null;

        if (array_key_exists("campaign", $user->meta)) {
            $model = $user->meta["campaign"]["model"];
            $rate = $user->meta["campaign"]["rate"];
        }
        
        $view->set([
            'limit' => $limit, 'page' => $page,
            'count' => $count, 'ads' => $ads,
            'model' => $model, 'rate' => $rate,
            'categories' => $categories, 'coverage' => $category,
            'tdomains' => \Shared\Services\User::trackingLinks($this->user, $this->org),
            'keyword' => $keyword, 'pads' => $pads
        ]);
    }

    /**
     * @before _secure
     */
    public function campaign($id) {
        $ad = \Ad::first(["_id = ?" => $id, 'org_id' => $this->org->_id]);
        if (!$ad) $this->_404();

        $this->seo(array("title" => $ad->title));
        $view = $this->getActionView();

        if (RM::get("action") == "permission" && array_key_exists("permission", $ad->meta)) {
            $search = [ "org_id" => $this->org->id, "ad_id" => $id, "user_id" => $this->user->id ];
            $access = AdAccess::first($search);
            // Check before saving to prevent duplication of records
            if (!$access) {
                $access = new AdAccess($search);
                $access->save();   
            }
        }

        if (RM::post("action")) { // action value already checked in _postback func
            $this->_postback('add', ['ad' => $ad]);
        }

        if (RM::type() === 'DELETE') {
            $this->_postback('delete');
        }

        $this->_postback('show', ['ad' => $ad]);
        $start = RM::get("start", date('Y-m-d', strtotime("-7 day")));
        $end = RM::get("end", date('Y-m-d'));
        $limit = RM::get("limit", 10); $page = RM::get("page", 1);
        $query = [
            'adid' => Db::convertType($id),
            'created' => Db::dateQuery($start, $end)
        ];
        $clicks = \Click::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Click::count($query);
        $cf = Utils::getConfig('cf', 'cloudflare');
        $view->set("domain", $cf->api->domain)
            ->set("clicks", $clicks)
            ->set("count", $count)
            ->set('advertiser', $this->user);

        $comms = Commission::all(["ad_id = ?" => $id]);$models = [];
        foreach ($comms as $comm) {
            $models[] = $comm->model;
        }
        $advertiser = User::first(["id = ?" => $ad->user_id], ['name']);
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);

        $view->set("ad", $ad)
            ->set("comms", $comms)
            ->set("categories", $categories)
            ->set("advertiser", $advertiser)
            ->set('models', $models)
            ->set("start", $start)
            ->set("end", $end)
            ->set('tdomains', \Shared\Services\User::trackingLinks($this->user, $this->org));
    }

    public function links() {
        $this->seo(array("title" => "Tracking Links")); $view = $this->getActionView();
        $links = Link::all(['user_id' => $this->user->_id], ['ad_id', 'domain', '_id']);

        $in = ArrayMethods::arrayKeys($links, 'ad_id');
        $ads = Ad::all(['_id' => ['$in' => $in]], ['title', '_id']);

        $result = [];
        foreach ($links as $l) {
            $adid = $l->ad_id;
            $result[] = ArrayMethods::toObject([
                'title' => $ads[$adid]->title,
                'url' => $l->getUrl()
            ]);
        }
        $view->set('links', $result);

        if ($this->defaultExtension === "csv") {
            $view->erase('start')->erase('end');
            $this->_org = $this->_user = null;
        }
    }

    /**
     * @before _secure
     */
    public function reports() {
        $this->seo(array("title" => "Campaign Reports")); $view = $this->getActionView();
        
        $query = [ "user_id" => Utils::mongoObjectId($this->user->_id) ];
        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-3 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $limit = RM::get("limit", 20);
        $page = RM::get("page", 1);

        $this->_reportspub($query, $start, $end, $limit, $page);
    }

    private function _reportspub($query, $start, $end, $limit, $page) {
        $view = $this->getActionView();
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $links = \Link::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Link::count($query);

        $query["created"] = Db::dateQuery($start, $end);
        $performances = \Performance::all($query, ['created', 'clicks', 'revenue'], 'created', 'desc');
        
        $in = [];
        foreach ($links as $l) {    // only find clicks for the ads whose links are created
            $in[] = Utils::mongoObjectId($l->ad_id);
        }
        // find clicks
        $clickCol = Registry::get("MongoDB")->clicks;
        $records = Db::query('Click', [
            'adid' => ['$in' => $in], 'is_bot' => false,
            'pid' => $query['user_id'], 'created' => $query['created']
        ], ['adid', 'country']);
        
        $view->set([
            'limit' => $limit, 'page' => $page,
            'count' => $count, 'start' => $start,
            'end' => $end, 'links' => $links,
            'performances' => $performances,
            'clicks' => Click::classify($records, 'adid'),
            'commission' => $this->user->commission(),
            'dq' => $query['created']
        ]);
    }

    /**
     * @before _secure
     */
    public function account() {
        $this->seo(array("title" => "Account"));
        $view = $this->getActionView();

        $invoices = \Invoice::all(['user_id = ?' => $this->user->_id], ['start', 'end', 'amount', 'live', 'created']);
        $payments = \Payment::all(['user_id = ?' => $this->user->_id], ['type', 'amount', 'meta', 'live', 'created']);
        $user = $this->user; $view->set("errors", []);
        if (RM::type() == 'POST') {
            $action = RM::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'phone', 'currency', 'username'];
                    foreach ($fields as $f) {
                        $user->$f = RM::post($f);
                    }
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RM::post('password');
                    $new = RM::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'bank':
                    $user->getMeta()['bank'] = [
                        'name' => RM::post('account_bank', ''),
                        'ifsc' => RM::post('account_code', ''),
                        'account_no' => RM::post('account_number', ''),
                        'account_owner' => RM::post('account_owner', '')
                    ];
                    $user->save();
                    $view->set('message', 'Bank Info Updated!!');
                    break;

                case 'payout':
                    $user->getMeta()['payout'] = [
                        'paypal' => RM::post('paypal', ''),
                        'payquicker' => RM::post('payquicker', ''),
                        'payoneer' => RM::post('payoneer', ''),
                        'paytm' => RM::post('paytm', ''),
                        'mobicash' => RM::post('mobicash', ''),
                        'easypaisa' => RM::post('easypaisa', '')
                    ];
                    $user->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;

                default:
                    $this->_postback('add');
                    break;
            }
            $this->setUser($user);
        }

        if (RM::type() === 'DELETE') {
            $this->_postback('delete');
        }
        $this->_postback('show');
        $afields = Meta::search('customField', $this->org);
        $view->set('afields', $afields)
            ->set("invoices", $invoices)
            ->set("payments", $payments);
    }

    /**
     * @before _verified
     */
    public function createLink() {
        $this->JSONView();
        $view = $this->getActionView();

        $adid = RM::post("adid");
        if (!$adid) return $view->set('message', "Invalid Request");
        $ad = \Ad::first(["_id = ?" => $adid, "live = ?" => true], ['_id', 'title']);
        if (!$ad) {
            return $view->set('message', "Invalid Request");
        }

        $user = User::first(["id = ?" => RM::post("aff_id")]) ?? $this->user;
        $tdomains = Shared\Services\User::trackingLinks($user, $this->org);
        if (RM::post("domain")) {
            $domain = RM::post("domain");
        } else {
            $domain = $this->array_random($tdomains);
        }
        $link = Link::first(["ad_id = ?" => $ad->_id, "user_id = ?" => $user->_id], ['domain', '_id']);
        if (!$link) {
            $link = new Link([
                'user_id' => $user->_id, 'ad_id' => $ad->_id,
                'domain' => $domain, 'live' => true
            ]);
            $link->save();
        }
        
        $view->set('message', $ad->title)
            ->set('link', $link->getUrl($domain));

    }

    protected function array_random($arr, $num = 1) {
        shuffle($arr);
        
        $r = array();
        for ($i = 0; $i < $num; $i++) {
            $r[] = $arr[$i];
        }
        return $num == 1 ? $r[0] : $r;
    }
    
    /**
     * @before _admin
     */
    public function add() {
        $this->seo(array("title" => "Add Publisher"));$view = $this->getActionView();

        $view->set("errors", []);
        if (RM::type() == 'POST') {
            $user = \User::addNew('publisher', $this->org, $view);
            if (!$user) return;
            if (RM::post('rate')) {
                $user->meta = [
                    'campaign' => [
                        'model' => RM::post('model'),
                        'rate' => $this->currency(RM::post('rate')),
                        'coverage' => ['ALL']
                    ]
                ];
            }
            $user->save();
            if (RM::post("notify") == "yes") {
                Mail::send([
                    'user' => $user,
                    'template' => 'pubRegister',
                    'subject' => 'Publisher at '. $this->org->name,
                    'org' => $this->org,
                    'pass' => $user->password
                ]);   
            }
            $user->password = sha1($user->password);
            $user->live = 1;
            $user->save();
            
            $this->redirect("/publisher/manage.html");
        }
    }

    /**
     * @before _admin
     */
    public function manage() {
        $this->seo(["title" => "List Publisher"]); $view = $this->getActionView();
        $page = RM::get("page", 1); $limit = RM::get("limit", 10);
        $publishers = [];

        $start = RM::get("start", date('Y-m-d')); $end = RM::get("end", date('Y-m-d'));
        $view->set(['start' => $start, 'end' => $end]);

        switch (RM::get("sort")) {
            case 'trending':
                $match = [
                    'pid' => ['$in' => $this->org->users('publisher')],
                    'is_bot' => false,
                    'created' => Db::dateQuery($start, $end)
                ];

                $clickCol = Db::collection('Click');
                $records = $clickCol->aggregate([
                    ['$match' => $match],
                    ['$project' => ['pid' => 1, '_id' => 0]],
                    ['$group' => [
                        '_id' => '$pid',
                        'count' => ['$sum' => 1]
                    ]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => (int) $limit]
                ]);
                foreach ($records as $r) {
                    $arr = Utils::toArray($r);
                    $id = Utils::getMongoID($arr['_id']);
                    $publishers[] = User::first(["id = ?" => $id]);
                }
                $count = $limit;
                break;
            
            default:
                $query = ["type = ?" => "publisher", "org_id = ?" => $this->org->_id];
                $property = RM::get("property");
                $value = RM::get("value");
                if (in_array($property, ["live", "id"])) {
                    $query["{$property} = ?"] = $value;
                } else if (in_array($property, ["email", "name", "phone"])) {
                    $query["{$property} = ?"] = Db::convertType($value, 'regex');
                }

                $publishers = \User::all($query, [], 'created', 'desc', $limit, $page);
                $count = \User::count($query);
                break;
        }

        $active = \User::count(["type = ?" => "publisher", "org_id = ?" => $this->org->_id, "live = ?" => 1]);
        $inactive = \User::count(["type = ?" => "publisher", "org_id = ?" => $this->org->_id, "live = ?" => 0]);

        $view->set("publishers", $publishers)
            ->set("property", $property)
            ->set("value", $value)
            ->set("active", $active)
            ->set("inactive", $inactive)
            ->set("count", $count)
            ->set("limit", $limit)
            ->set("page", $page);
    }

    /**
     * @before _admin
     */
    public function update($id) {
        $this->JSONView(); $view = $this->getActionView();
        $p = \User::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$p || RM::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }

        foreach ($_POST as $key => $value) {
            $p->$key = $value;
        }
        $p->save();
        $view->set('message', 'Updated successfully!!');
    }

    /**
     * @protected
     */
    public function _admin() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/admin");
    }

    /**
     * @before _secure
     */
    public function platforms() {
        $this->seo(array("title" => "List of Platforms")); $view = $this->getActionView();

        $nativeCode = $this->getAdCode('native', true);
        $bannerCode = $this->getAdCode('banner', true);
        $view->set('nativeCode', $nativeCode);
        $view->set('bannerCode', $bannerCode);

        if (RM::type() === 'POST') {
            $pid = RM::post('pid');
            try {
                if ($pid) {
                    $p = \Platform::first(['_id = ?' => $pid]);
                } else {
                    $p = new \Platform([
                        'org_id' => $this->org->id,
                        'user_id' => $this->user->_id
                    ]);
                }
                $p->url = RM::post('url');
                $p->category = RM::post('category');
                $p->type = RM::post('type');
                $p->verified = false;
                $p->save();

                $view->set('message', 'Platform saved successfully!!');
            } catch (\Exception $e) {
                $view->set('message', $e->getMessage());
            }
        }
        $platforms = \Platform::all(["user_id = ?" => $this->user->_id]);
        $view->set("platforms", $platforms);
        $categories = \Category::all(['org_id' => $this->org->_id]);
        $view->set('categories', $categories);
    }

    /**
     * @before _admin
     */
    public function info($id = null) {
        $this->seo(array("title" => "Publisher Edit"));
        $view = $this->getActionView();

        $publisher = User::first(["_id = ?" => $id, "type = ?" => "publisher", "org_id = ?" => $this->org->id]);
        if (!$publisher) $this->_404();
        $platforms = Platform::all(["user_id = ?" => $publisher->id]);
        $view->set("platforms", $platforms);
        
        $view->set("errors", []);
        if (RM::type() == 'POST') {
            $action = RM::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'email', 'phone', 'country', 'currency', 'username'];
                    foreach ($fields as $f) {
                        $publisher->$f = RM::post($f);
                    }
                    $publisher->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RM::post('password');
                    $new = RM::post('npassword');
                    $view->set($publisher->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $publisher->getMeta()['campaign'] = [
                        'model' => RM::post('model'),
                        'rate' => $this->currency(RM::post('rate'))
                    ];
                    $publisher->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                case 'trackingDomain':
                    $tdomain = (array) RM::post('tdomain', '');
                    if ($tdomain && ArrayMethods::inArray($this->org->tdomains, $tdomain)) {
                        $publisher->getMeta()['tdomain'] = $tdomain;
                        $publisher->save();

                        $view->set('message', 'Added Tracking Domain for publisher');
                    } else {
                        $view->set('message', 'Invalid Request!!');
                    }
                case 'commadd':
                case 'commedit':
                    $comm_id = RM::post('comm_id');
                    if ($comm_id) {
                        $comm = Commission::first(['_id' => $comm_id, 'user_id' => $publisher->_id]);
                    } else {
                        $comm = new Commission([
                            'user_id' => $publisher->_id
                        ]);
                    }
                    $comm->model = RM::post('model');
                    $comm->description = RM::post('description');
                    $comm->rate = $this->currency(RM::post('rate'));
                    $comm->coverage = RM::post('coverage', ['ALL']);
                    $comm->save();
                    $view->set('message', "Multi Country Payout Saved!!");
                    break;
            }
        }

        if (RM::type() === 'DELETE') {
            $action = RM::get("action");
            switch ($action) {
                case 'payoutdel':
                    unset($publisher->getMeta()['campaign']);
                    $publisher->save();
                    $view->set('message', 'Payout Deleted!!');
                    break;

                case 'commDel':
                    $comm = Commission::first(['_id' => RM::get("comm_id"), 'user_id' => $publisher->_id]);
                    if ($comm) {
                        $comm->delete();
                        $view->set('message', 'Payout Deleted!!');
                    } else {
                        $view->set('message', 'Invalid Request!!');
                    }
                    break;
                
                case 'afields':
                    $meta = $publisher->meta; $publisher->removeFields();
                    unset($meta['afields']);
                    Db::updateRaw('users', [
                        '_id' => Db::convertType($publisher->_id, 'id')
                    ], ['$set' => ['meta' => $meta]]);
                    $view->set('message', 'Data Removed!!');
                    break;

                case 'defaultDomain':
                    unset($publisher->getMeta()['tdomain']);
                    $publisher->save();
                    $view->set('message', 'Removed tracking domain!!');
                    break;
            }
        }

        $afields = Meta::search('customField', $this->org);
        $view->set('afields', $afields)
            ->set("publisher", $publisher)
            ->set("commissions", Commission::all(["user_id = ?" => $publisher->id]))
            ->set("start", strftime("%Y-%m-%d", strtotime('-7 day')))
            ->set("end", strftime("%Y-%m-%d", strtotime('now')))
            ->set("d", Performance::total(['start' => ($start ?? $publisher->created->format('Y-m-d')), 'end' => ($end ?? date('Y-m-d'))], $publisher));
    }

    /**
     * @before _admin
     */
    public function delete($pid) {
        parent::delete($pid); $view = $this->getActionView();
        $user = \User::first(["_id" => $pid, 'type' => 'publisher', 'org_id' => $this->org->_id]);
        if (!$user) {
            $this->_404();
        }

        $result = $user->delete();
        if ($result) {
            $view->set('message', 'Publisher removed successfully!!');
        } else {
            $view->set('message', 'Failed to delete. Publisher has already given clicks!!');   
        }
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'publisher' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/publisher");
    }

    /**
     * @before _session
     * @after _csrfToken
     */
    public function register() {
        $this->seo(array("title" => "Publisher Register", "description" => "Register"));
        $view = $this->getActionView(); $view->set('errors', []);

        $afields = Meta::search('customField', $this->org);
        $view->set('afields', $afields ?? []);
        $token = RM::post("token", '');
        if (RM::post("action") == "register" && $this->verifyToken($token)) {
            $this->_publisherRegister($this->org, $view);
        }
    }

    /**
     * @before _secure
     */
    public function contests() {
        $this->seo(array("title" => "Publisher Register", "description" => "Register"));
        $view = $this->getActionView();

        $contests = \Contest::all([
            'org_id' => $this->org->_id
        ]);

        $view->set('contests', $contests);
    }

    /**
     * @before _secure
     */
    public function getAdCode($type = 'native', $internal = false) {
        $code = '<script>(function (we, a, r, e, vnative){we["vNativeObject"]=vnative;we[vnative]=we[vnative]||function(){(i[vnative].q=i[r].q || []).push(arguments)};var x,y;x=a.createElement(r),y=a.getElementsByTagName(r)[0];x.async=true;x.src=e;y.parentNode.insertBefore(x, y);}(window,document,"script","//serve.vnative.com/js/native.js","vn"));
            </script><ins class="byvnative" data-client="pub-'. Utils::getMongoID($this->user->id) .'" data-format="all" ';

        switch ($type) {
            case 'banner':
                $code .= 'data-type="banner" data-width="300" data-height="200"';
                break;
            
            default:
                $code .= 'data-type="native"';
                break;
        }
        
        $code .= '></ins>';

        if (!$internal) {
            $this->JSONview(); $view = $this->getActionView();
            $view->set('code', $code);
        } else {
            return $code;
        }
    }
}
