<?php
/**
 * Controller to manage publisher actions
 *
 * @author Faizan Ayubi
 */
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Publisher extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView(); $commissions = []; $clicks = 0;$d = 1;

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery($start, $end);
        $clickCol = Registry::get("MongoDB")->clicks;
        $clicks = Db::query('Click', [
            "pid" => $this->user->_id, "is_bot" => false,
            "created" => Db::dateQuery($start, $end)
        ], ['adid', 'country']);

        $notifications = Notification::all([
            "org_id = ?" => $this->org->id,
            "meta = ?" => ['$in' => ['all', $this->user->_id]]
        ], [], "created", "desc", 5, 1);
        
        $total = Performance::overall(
            Utils::dateQuery([
                'start' => strftime("%Y-%m-%d", strtotime('-365 day')),
                'end' => strftime("%Y-%m-%d", strtotime('-1 day'))
            ]),
            $this->user
        );
        $topusers = $this->widgets($dateQuery);
        if (array_key_exists("widgets", $this->org->meta)) {
            $d = isset($notifications) + in_array("top10ads", $this->org->meta["widgets"]) + in_array("top10pubs", $this->org->meta["widgets"]);
        }

        $view->set("start", $start)
            ->set("end", $end)
            ->set("d", 12/$d)
            ->set("topusers", $topusers)
            ->set("notifications", $notifications)
            ->set("total", $total)
            ->set("yesterday", strftime("%B %d, %Y", strtotime('-1 day')))
            ->set("performance", $this->perf($clicks, ['type' => 'publisher', 'publisher' => $this->user], ['start' => $start, 'end' => $end]));
    }

    /**
     * @before _secure
     */
    public function campaigns() {
    	$this->seo(array("title" => "Campaigns"));$view = $this->getActionView();

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);
        $category = RequestMethods::get("category", []);
        $keyword = RequestMethods::get("keyword", '');
        $query = ["live = ?" => true, "org_id = ?" => $this->org->_id];
        $today = date('Y-m-d');

        if (count($category) > 0) {
            // if you want AND query instead of OR query then replace '$in' --> '$all'
            $query["category"] = ['$in' => Ad::setCategories($category)];
        }
        if ($keyword) {
            $query["title"] = Utils::mongoRegex($keyword);
        }
        $orderBy = RequestMethods::get("orderBy", 'desc'); $order = 'desc';

        if (in_array($orderBy, ['asc', 'desc'])) {
            $count = \Ad::count($query); $cats = [];
            $order = $orderBy;
            $ads = \Ad::all($query, [], 'modified', $order, $limit, $page);
        } else if ($orderBy == "viral") {
            $ads = \Ad::all($query, [], 'modified', $order);
            $fields = Shared\Services\User::fields('Ad');
            $ids = array_keys($ads); $ads = Ad::objectArr($ads, $fields);

            $clickCol = Registry::get("MongoDB")->clicks;
            $results = $clickCol->aggregate([
                ['$match' => [
                    'adid' => ['$in' => Db::convertType($ids, 'id')],
                    'is_bot' => false,
                    'created' => Db::dateQuery($today, $today),
                ]],
                ['$project' => ['adid' => 1, '_id' => 1]],
                ['$group' => ['_id' => '$adid', 'count' => ['$sum' => 1]]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 30]
            ]);

            $ans = []; 
            foreach ($results as $r) {
                $a = (object) $r; $key = Utils::getMongoID($a->_id);
                $ans[$key] = $ads[$key];
            }
            $ads = $ans; $count = 30;
        }
        $query["meta.private"] = ['$ne' => true];
    	
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
            'keyword' => $keyword, 'orderBy' => $orderBy
        ]);
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
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-3 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('-1 day')));

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);

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

        $invoices = \Invoice::all(['user_id = ?' => $this->user->_id], ['start', 'end', 'period', 'amount', 'live', 'created']);
        $payments = \Payment::all(['user_id = ?' => $this->user->_id], ['type', 'amount', 'meta', 'live', 'created']);
        $user = $this->user; $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'phone', 'currency'];
                    foreach ($fields as $f) {
                        $user->$f = RequestMethods::post($f);
                    }
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'bank':
                    $user->getMeta()['bank'] = [
                        'name' => RequestMethods::post('account_bank', ''),
                        'ifsc' => RequestMethods::post('account_code', ''),
                        'account_no' => RequestMethods::post('account_number', ''),
                        'account_owner' => RequestMethods::post('account_owner', '')
                    ];
                    $user->save();
                    $view->set('message', 'Bank Info Updated!!');
                    break;

                case 'payout':
                    $user->getMeta()['payout'] = [
                        'paypal' => RequestMethods::post('paypal', ''),
                        'payquicker' => RequestMethods::post('payquicker', ''),
                        'payoneer' => RequestMethods::post('payoneer', ''),
                        'paytm' => RequestMethods::post('paytm', ''),
                        'mobicash' => RequestMethods::post('mobicash', ''),
                        'easypaisa' => RequestMethods::post('easypaisa', '')
                    ];
                    $user->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
            $this->setUser($user);
        }
        $afields = Meta::search('customField', $this->org);
        $view->set('afields', $afields)
            ->set("invoices", $invoices)
            ->set("payments", $payments);
    }

    /**
     * @before _secure
     */
    public function createLink() {
        $this->JSONView();
        $view = $this->getActionView();

        $adid = RequestMethods::post("adid");
        if (!$adid) return $view->set('message', "Invalid Request");
        $ad = \Ad::first(["_id = ?" => $adid, "live = ?" => true], ['_id', 'title']);
        if (!$ad) {
            return $view->set('message', "Invalid Request");
        }

        $tdomains = Shared\Services\User::trackingLinks($this->user, $this->org);
        if (RequestMethods::post("domain")) {
            $domain = RequestMethods::post("domain");
        } else {
            $domain = $this->array_random($tdomains);
        }
        $link = Link::first(["ad_id = ?" => $ad->_id, "user_id = ?" => $this->user->_id], ['domain', '_id']);
        if (!$link) {
            $link = new Link([
                'user_id' => $this->user->_id, 'ad_id' => $ad->_id,
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
        if (RequestMethods::type() == 'POST') {
            $user = \User::addNew('publisher', $this->org, $view);
            if (!$user) return;
            if (RequestMethods::post('rate')) {
                $user->meta = [
                    'campaign' => [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate')),
                        'coverage' => ['ALL']
                    ]
                ];
            }
            $user->save();
            if (RequestMethods::post("notify") == "yes") {
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
        $this->seo(array("title" => "List Publisher"));$view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $query = ["type = ?" => "publisher", "org_id = ?" => $this->org->_id];
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);
        if (in_array($property, ["live", "id"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["email", "name", "phone"])) {
            $query["{$property} = ?"] = Utils::mongoRegex($value);
        }

        $publishers = \User::all($query, [], 'created', 'desc', $limit, $page);
        $count = \User::count($query);
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
        if (!$p || RequestMethods::type() !== 'POST') {
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

        if (RequestMethods::type() === 'POST') {
            $pid = RequestMethods::post('pid');
            try {
                if ($pid) {
                    $p = \Platform::first(['_id = ?' => $pid]);
                } else {
                    $p = new \Platform([
                        'user_id' => $this->user->_id
                    ]);
                }
                $p->url = RequestMethods::post('url');
                $meta = $p->meta;
                $meta['category'] = RequestMethods::post('category', ['386']);
                $meta['type'] = RequestMethods::post('type', '');
                $p->meta = $meta;
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
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'email', 'phone', 'country', 'currency'];
                    foreach ($fields as $f) {
                        $publisher->$f = RequestMethods::post($f);
                    }
                    $publisher->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($publisher->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $publisher->getMeta()['campaign'] = [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate'))
                    ];
                    $publisher->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                case 'trackingDomain':
                    $tdomain = RequestMethods::post('tdomain');
                    if ($tdomain && in_array($tdomain, $this->org->tdomains)) {
                        $publisher->getMeta()['tdomain'] = $tdomain;
                        $publisher->save();

                        $view->set('message', 'Added Tracking Domain for publisher');
                    } else {
                        $view->set('message', 'Invalid Request!!');
                    }
                default:
                    break;
            }
        }

        if (RequestMethods::type() === 'DELETE') {
            $action = RequestMethods::get("action");
            switch ($action) {
                case 'payoutdel':
                    $meta = $publisher->meta;
                    unset($meta['campaign']);
                    if (empty($meta)) {
                        $publisher->meta = "";
                    } else {
                        $publisher->meta = $meta;
                    }
                    $publisher->save();
                    $view->set('message', 'Payout Deleted!!');
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

        $view->set("publisher", $publisher);
        $afields = Meta::search('customField', $this->org);
        $view->set('afields', $afields)
            ->set("start", strftime("%Y-%m-%d", strtotime('-7 day')))
            ->set("end", strftime("%Y-%m-%d", strtotime('now')));
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
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "register" && $this->verifyToken($token)) {
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