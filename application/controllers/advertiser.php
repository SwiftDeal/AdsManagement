<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Shared\Services\Db as Db;

class Advertiser extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView();$commissions = []; $clicks = 0;$d = 1;

        $ads = Ad::all([
            "org_id = ?" => $this->org->_id, "user_id = ?" => $this->user->id
        ], ["_id"]);
        $in = array_keys($ads);

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery($start, $end);
        $clickCol = Registry::get("MongoDB")->clicks;
        $clicks = Db::query('Click', [
            "adid" => ['$in' => $in], "is_bot" => false,
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
        
        $view->set("start", strftime("%Y-%m-%d", strtotime('-7 day')))
            ->set("end", strftime("%Y-%m-%d", strtotime('now')))
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
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);

        $query = [ "user_id" => $this->user->id ];
        $ads = \Ad::all($query, ['title', 'image', 'category', '_id', 'live', 'created'], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query);
        $in = Utils::mongoObjectId(array_keys($ads));

        $query["created"] = Db::dateQuery($start, $end);
        $records = Db::query('Click', [
            'adid' => ['$in' => $in],
            'is_bot' => false,
            'created' => $query["created"]
        ], ['adid']);

        $view->set("ads", $ads);
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set([
            'count' => $count, 'page' => $page,
            'limit' => $limit,
            'dateQuery' => $query['created'],
            'clicks' => Click::classify($records, 'adid')
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

        $start = RequestMethods::get("start", date('Y-m-d', strtotime("-7 day")));
        $end = RequestMethods::get("end", date('Y-m-d'));
        $limit = RequestMethods::get("limit", 10); $page = RequestMethods::get("page", 1);
        $quesry = [
            'adid' => Db::convertType($id),
            'created' => Db::dateQuery($start, $end)
        ];
        $clicks = \Click::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Click::count($query);
        $cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;
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
            ->set("end", $end);
    }

    /**
     * @before _secure
     */
    public function account() {
        $this->seo(array("title" => "Account"));
        $view = $this->getActionView();

        $user = $this->user;
        if (RequestMethods::type() === 'POST') {
            $action = RequestMethods::post('action');
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
            }
        }
    }

    /**
     * @before _secure
     */
    public function bills() {
        $this->seo(array("title" => "Bills"));
        $view = $this->getActionView();

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $query = [
            'user_id = ?' => $this->user->_id,
            'created = ?' => Db::dateQuery($start, $end)
        ];

        $performances = \Performance::all($query, [], 'created', 'desc');
        $invoices = \Invoice::all(['user_id = ?' => $this->user->_id]);
        $view->set("performances", $performances)
            ->set("invoices", $invoices);

        $view->set("start", $start);
        $view->set("end", $end);
    }

    /**
     * @before _admin
     */
    public function add() {
        $this->seo(array("title" => "Add Advertiser"));$view = $this->getActionView();
        $pass = Shared\Utils::randomPass();
        $view->set("pass", $pass);
        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $user = \User::addNew('advertiser', $this->org, $view);
            if (!$user) return;
            $user->meta = [
                'campaign' => [
                    'model' => RequestMethods::post('model'),
                    'rate' => $this->currency(RequestMethods::post('rate')),
                    'coverage' => ['ALL']
                ]
            ];
            $user->save();

            if (RequestMethods::post("notify") == "yes") {
                Mail::send([
                    'user' => $user,
                    'template' => 'advertReg',
                    'subject' => $this->org->name . 'Support',
                    'org' => $this->org
                ]);
            }

            $user->password = sha1($user->password);
            $user->live = 1;
            $user->save();
            $this->redirect("/advertiser/manage.html");
        }
    }

    /**
     * @before _admin
     */
    public function manage() {
        $this->seo(array("title" => "Manage")); $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 30);
        $query = ["type = ?" => "advertiser", "org_id = ?" => $this->org->_id];
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);
        if (in_array($property, ["live", "id"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["email", "name", "phone"])) {
            $query["{$property} = ?"] = Utils::mongoRegex($value);
        }

        $advertisers = \User::all($query, ['_id', 'name', 'live', 'email', 'created'], 'created', 'desc');
        $count = \User::count($query);
        $active = \User::count(["type = ?" => "advertiser", "org_id = ?" => $this->org->_id, "live = ?" => 1]);
        $inactive = \User::count(["type = ?" => "advertiser", "org_id = ?" => $this->org->_id, "live = ?" => 0]);

        $view->set("advertisers", $advertisers)
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
    public function info($id = null) {
        $this->seo(array("title" => "Advertiser Edit"));
        $view = $this->getActionView();

        $advertiser = User::first(["_id = ?" => $id, "type = ?" => "advertiser", "org_id = ?" => $this->org->id]);
        if (!$advertiser) $this->_404();

        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'email', 'phone', 'country', 'currency'];
                    foreach ($fields as $f) {
                        $advertiser->$f = RequestMethods::post($f);
                    }
                    $advertiser->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($advertiser->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $advertiser->getMeta()['campaign'] = [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate'))
                    ];
                    $advertiser->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
        }
        $view->set("advertiser", $advertiser);
    }

    /**
     * @before _admin
     */
    public function update($id) {
        $this->JSONView(); $view = $this->getActionView();
        $a = \User::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$a || RequestMethods::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }

        $updateAble = ['live', 'name'];
        foreach ($_POST as $key => $value) {
            if (in_array($key, $updateAble)) {
                $a->$key = $value;   
            }
        }
        $a->save();
        $view->set('message', 'Updated successfully!!');
    }

    /**
     * @protected
     */
    public function _admin() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->_404();
        }
        $this->setLayout("layouts/admin");
    }

    /**
     * Returns data of clicks, impressions, payouts for publishers with custom date range
     * @before _secure
     */
    public function performance() {
        $this->JSONview();$view = $this->getActionView();
        
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        
        $find = Performance::overall($dateQuery, $this->user);
        $view->set($find);
    }

    /**
     * @before _secure
     */
    public function impressions() {
        $this->JSONview();
        $view = $this->getActionView();
    }

    /**
     * @before _admin
     */
    public function delete($pid) {
        parent::delete($pid); $view = $this->getActionView();
        
        $user = \User::first(["_id" => $pid, 'type' => 'advertiser', 'org_id' => $this->org->_id]);
        if (!$user) $this->_404();

        $result = $user->delete();
        if ($result) {
            $view->set('message', 'Advertiser Deleted successfully!!');
        } else {
            $view->set('message', 'Failed to delete the advetiser data from database!!');   
        }
    }

    /**
     * @before _secure
     */
    public function platforms() {
        $this->seo(array("title" => "List of Platforms")); $view = $this->getActionView();

        if (RequestMethods::type() === 'POST') {
            $pid = RequestMethods::post('pid');
            try {
                if ($pid) {
                    $p = \Platform::first(['_id = ?' => $pid]);
                } else {
                    $p = new \Platform([
                        'user_id' => $this->user->_id,
                        'live' => true
                    ]);
                }
                $p->url = RequestMethods::post('url');
                $p->save();
                $view->set('message', 'Platform saved successfully!!');
            } catch (\Exception $e) {
                $view->set('message', $e->getMessage());
            }
        }

        $platforms = \Platform::all(["user_id = ?" => $this->user->_id], ['_id', 'url']);
        $results = [];

        $start = RequestMethods::get("start", date('Y-m-d', strtotime('-7 day')));
        $end = RequestMethods::get("end", date('Y-m-d', strtotime('-1 day')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        foreach ($platforms as $p) {
            $key = Utils::getMongoID($p->_id);

            $stats = \Stat::all([
                'pid' => $key,
                'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
            ], ['clicks', 'revenue', 'cpc']);
            $clicks = 0; $revenue = 0.00; $cpc = 0.00;
            foreach ($stats as $s) {
                $clicks += $s->clicks;
                $revenue += $s->revenue;
            }

            if ($clicks !== 0) {
                $cpc = round($revenue / $clicks, 4);
            }
            $results[$key] = ArrayMethods::toObject([
                '_id' => $p->_id,
                'url' => $p->url,
                'stats' => [
                    'clicks' => $clicks,
                    'revenue' => $revenue,
                    'cpc' => $cpc
                ]
            ]);
        }

        $view->set("platforms", $results)
            ->set("start", $start)
            ->set("end", $end);
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'advertiser' || !$this->org) {
            $this->_404();
        }
        $this->setLayout("layouts/advertiser");
    }

    /**
     * @before _session
     * @after _csrfToken
     */
    public function register() {
        $this->seo(array("title" => "Advertiser Register", "description" => "Register"));
        $view = $this->getActionView();

        $view->set('errors', []);
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "register" && $this->verifyToken($token)) {
            $this->_advertiserRegister($this->org, $view);
        }
    }
}