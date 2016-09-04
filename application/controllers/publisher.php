<?php
/**
 * Controller to manage publisher actions
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Publisher extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView(); $commissions = []; $clicks = 0;$d = 1;

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $clickCol = Registry::get("MongoDB")->clicks;
        $clicks = $clickCol->find([
            "pid" => Utils::mongoObjectId($this->user->id), "is_bot" => false,
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ], ['projection' => ['adid' => 1]]);

        $notifications = Notification::all(["org_id = ?" => $this->org->id], [], "created", "desc", 5, 1);
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
            ->set("performance", $this->perf($clicks, $this->user, $this->org, $dateQuery));
    }

    /**
     * @before _secure
     */
    public function campaigns() {
    	$this->seo(array("title" => "Campaigns"));$view = $this->getActionView();

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);
        $coverage = RequestMethods::get("coverage", []);
        $query = ["live = ?" => true, "org_id = ?" => $this->org->_id];

        if ($coverage) {
            // if you want AND query instead of OR query then replace '$in' --> '$all'
            $query["category"] = ['$in' => Ad::setCategories($coverage)];
        }
    	
        $ads = \Ad::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query); $cats = [];
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
            'categories' => $categories, 'coverage' => $coverage
        ]);
    }

    /**
     * @before _secure
     */
    public function reports() {
        $this->seo(array("title" => "Campaigns"));$view = $this->getActionView();
        
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);

        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [ "user_id" => Utils::mongoObjectId($this->user->_id) ];
        $links = \Link::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Link::count($query);

        $query["created"] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        $performances = \Performance::all($query, ['created', 'clicks', 'revenue'], 'created', 'desc');
        
        $in = [];
        foreach ($links as $l) {    // only find clicks for the ads whose links are created
            $in[] = Utils::mongoObjectId($l->ad_id);
        }
        // find clicks
        $clickCol = Registry::get("MongoDB")->clicks;
        $records = $clickCol->find([
            'adid' => ['$in' => $in], 'is_bot' => false,
            'pid' => $query['user_id'], 'created' => $query['created']
        ], ['projection' => ['adid' => 1]]);
        
        $view->set([
            'limit' => $limit, 'page' => $page,
            'count' => $count, 'start' => $start,
            'end' => $end, 'links' => $links,
            'performances' => $performances,
            'clicks' => $records,
            'commission' => $this->user->commission()
        ]);
    }

    /**
     * @before _secure
     */
    public function account() {
        $this->seo(array("title" => "Account"));
        $view = $this->getActionView();

        $transactions = \Transaction::all(['user_id = ?' => $this->user->_id], ['amount', 'currency', 'ref', 'created']);
        $view->set("transactions", $transactions);
        $user = $this->user; $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $name = RequestMethods::post('name');
                    $currency = RequestMethods::post('currency', 'INR');

                    $user->name = $name; $user->currency = $currency;
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'bank':
                    $meta = $user->getMeta();
                    $meta['bank'] = [
                        'name' => RequestMethods::post('account_bank', ''),
                        'ifsc' => RequestMethods::post('account_code', ''),
                        'account_no' => RequestMethods::post('account_number', ''),
                        'account_owner' => RequestMethods::post('account_owner', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Bank
                     Info Updated!!');
                    break;

                case 'payout':
                    $meta = $user->getMeta();
                    $meta['payout'] = [
                        'paypal' => RequestMethods::post('paypal', ''),
                        'payoneer' => RequestMethods::post('payoneer', ''),
                        'paytm' => RequestMethods::post('paytm', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
            $this->setUser($user);
        }
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
        if (RequestMethods::post("domain")) {
            $domain = RequestMethods::post("domain");
        } else if (count($this->org->tdomains) > 0) {
            $domain = $this->array_random($this->org->tdomains);
        } else {
            $domain = 'dobolly.com';
        }
        $link = Link::first(["ad_id = ?" => $ad->_id, "user_id = ?" => $this->user->_id], ['domain', '_id']);
        if ($link) {
            $view->set('message', $ad->title.' <br><a href="http://'.$domain.'/'.$link->_id.'" target="_blank">http://'.$domain.'/'.$link->_id.'<a>');
            return;
        }

        $link = new Link([
            'user_id' => $this->user->_id,
            'ad_id' => $ad->_id,
            'domain' => $domain,
            'live' => true
        ]);
        $link->save();
        $view->set('message', $ad->title.'<br><a href="'.$link->getUrl().'" target="_blank">'.$link->getUrl().'<a>');

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
    public function payments() {
        $this->seo(array("title" => "Payments"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $users = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id]);
        $payments = [];
        foreach ($users as $u) {
            $lastTransaction = \Transaction::first(['user_id = ?' => $u->_id], [], 'created', 'desc');

            $query = ['user_id = ?' => $u->_id];
            if ($lastTransaction) {
                $query['created'] = ['$gt' => $lastTransaction->created];
            }
            $performances = \Performance::all($query);
            $payment = 0.00;
            foreach ($performances as $p) {
                $payment += $p->revenue;
            }

            $payments[] = ArrayMethods::toObject([
                'user_id' => $u->getMongoID(),
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'amount' => $payment,
                'bank' => isset($u->meta['bank']) ? $u->meta['bank'] : []
            ]);
        }
        
        $view->set('payments', $payments);
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
        $limit = RequestMethods::get("limit", 30);
        $query = ["type = ?" => "publisher", "org_id = ?" => $this->org->_id];
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);
        if (in_array($property, ["live"])) {
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
    public function info($id) {
        $this->seo(array("title" => "Publisher Edit"));
        $view = $this->getActionView();

        $publisher = User::first(["_id = ?" => $id, "type = ?" => "publisher", "org_id = ?" => $this->org->id]);
        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $publisher->name = RequestMethods::post('name');
                    $publisher->email = RequestMethods::post('email');
                    $publisher->phone = RequestMethods::post('phone');
                    $publisher->country = RequestMethods::post('country');
                    $publisher->currency = RequestMethods::post('currency');
                    $publisher->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($publisher->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $meta = $publisher->getMeta();
                    $meta['campaign'] = [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate'))
                    ];
                    $publisher->meta = $meta;
                    $publisher->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
        }
        $view->set("publisher", $publisher);
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
        $clicks = \Click::count(["pid = ?" => $user->_id]);
        if ($clicks === 0) {
            $query = ['user_id' => $user->_id];
            $user->delete();
            \Link::deleteAll($query);
            \Performance::deleteAll($query);

            $view->set('message', 'Publisher removed successfully!!');
        } else {
            $view->set('message', 'Failed to delete. Publisher has already given clicks!!');
        }
    }

    protected function widgets($dateQuery = null) {
        if (!$dateQuery) {
            $date = RequestMethods::get("date", date('Y-m-d'));
            $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
        } $meta = $this->org->meta;
        if (isset($meta['widget']) && isset($meta['widget']['top10pubs']) && count($meta['widget']['top10pubs']) > 0) {
            $widgets = $meta['widget'];
            return [
                'publishers' => $widgets['top10pubs'] ?? [],
                'ads' => Ad::displayData($widgets['top10ads'] ?? [])
            ];
        } else { // fallback case
            return [
                'publishers' => [],
                'ads' => []
            ];
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
     */
    public function register() {
        $this->seo(array("title" => "Publisher Register", "description" => "Register"));
        $view = $this->getActionView(); $view->set('errors', []);
        $session = Registry::get("session");

        $csrf_token = $session->get('Publisher\Register:$token');
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "register" && $csrf_token && $token === $csrf_token) {
            $this->_publisherRegister($this->org, $view);
        }
        $csrf_token = Framework\StringMethods::uniqRandString(44);
        $session->set('Publisher\Register:$token', $csrf_token);
        $view->set('__token', $csrf_token);
        $view->set('organization', $this->org);
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