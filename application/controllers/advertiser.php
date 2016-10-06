<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;

class Advertiser extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", date('Y-m-d', strtotime('-7 day')));
        $end = RequestMethods::get("end", date('Y-m-d'));

        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        $transactions = \Transaction::all([
            "user_id = ?" => $this->user->_id,
            "created = ?" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ], ['amount']);

        $paid = 0.00;
        foreach ($transactions as $t) {
            $paid += $t->amount;
        }

        $view->set("start", $start);
        $view->set("end", $end)
            ->set("paid", $paid);
    }

    /**
     * @before _secure
     */
    public function campaigns() {
        $this->seo(array("title" => "Campaigns"));$view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);

        $in = [];
        $query = [
            "user_id" => $this->user->id
        ];
        $ads = \Ad::all($query, ['title', 'image', 'category', '_id', 'live', 'created'], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query);

        foreach ($ads as $a) {
            $in[] = Utils::mongoObjectId($a->_id);
        }

        $query["created"] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        $clickCol = Registry::get("MongoDB")->clicks;
        $records = $clickCol->find([
            'adid' => ['$in' => $in],
            'is_bot' => false,
            'created' => $query["created"]
        ], ['projection' => ['adid' => 1]]);

        $view->set("ads", $ads);
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set([
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
            'dateQuery' => $query['created'],
            'clicks' => Click::classify($records, 'adid')
        ]);
    }

    /**
     * @before _admin
     */
    public function billing() {
        $this->seo(array("title" => "Billing"));$view = $this->getActionView();

        $start = date('Y-m-d', strtotime('-7 day')); $end = date('Y-m-d', strtotime('-1 day'));
        $start = RequestMethods::get('start', $start);
        $end = RequestMethods::get('end', $end);
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        $users = \User::all(['type' => 'advertiser', 'org_id' => $this->org->_id]);
        $advertisers = [];
        foreach ($users as $u) {
            $perf = \Performance::calculate($u, $dateQuery);

            $advertisers[] = ArrayMethods::toObject([
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'amount' => $perf['revenue']
            ]);
        }

        $view->set('advertisers', $advertisers);
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
                    $name = RequestMethods::post('name');
                    $phone = RequestMethods::post('phone');
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

                case 'campaign':
                    $meta = $user->meta;
                    $rate = RequestMethods::post('rate', 0.25);
                    $meta['campaign'] = [
                        'model' => RequestMethods::post('model', 'cpc'),
                        'rate' => $this->currency($rate),
                    ];
                    $user->meta = $meta;
                    $user->save();
                    $view->set('message', 'Campaign Settings Updated!!');
                    break;
            }
        }
    }

    /**
     * @before _secure
     */
    public function payments() {
        $this->seo(array("title" => "Payments"));
        $view = $this->getActionView();

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        $query = ['user_id = ?' => $this->user->_id];
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $performances = \Performance::all($query, [], 'created', 'desc');
        $transactions = \Transaction::all($query, ['amount', 'currency', 'ref', 'created']);
        $view->set("performances", $performances)
            ->set("transactions", $transactions);

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
        if (in_array($property, ["live"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["email", "name", "phone"])) {
            $query["{$property} = ?"] = Utils::mongoRegex($value);
        }

        $advertisers = \User::all($query, [], 'created', 'desc');
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
    public function info($id) {
        $this->seo(array("title" => "Advertiser Edit"));
        $view = $this->getActionView();

        $advertiser = User::first(["_id = ?" => $id, "type = ?" => "advertiser", "org_id = ?" => $this->org->id]);
        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $advertiser->name = RequestMethods::post('name');
                    $advertiser->email = RequestMethods::post('email');
                    $advertiser->phone = RequestMethods::post('phone');
                    $advertiser->country = RequestMethods::post('country');
                    $advertiser->currency = RequestMethods::post('currency');
                    $advertiser->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($advertiser->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $meta = $advertiser->getMeta();
                    $meta['campaign'] = [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate'))
                    ];
                    $advertiser->meta = $meta;
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

        $ads = \Ad::all(['user_id = ?' => $user->_id], ['_id']);
        if (count($ads) === 0) {
            $user->delete();
            \Platform::deleteAll(['user_id' => $user->_id]);
            $view->set('message', 'Advertiser Deleted successfully!!');
        } else {
            $in = []; $query = ['user_id' => $user->_id];
            foreach ($ads as $a) {
                $in[] = Utils::mongoObjectId($a->_id);
            }
            // find clicks for any of the ad
            $clickCount = \Click::count(['ad_id' => ['$in' => $in]]);

            if ($clickCount === 0) {
                \Ad::deleteAll($query);
                \Commission::deleteAll(['ad_id' => ['$in' => $in]]);
                \Performance::deleteAll($query);
                \Platform::deleteAll(['user_id' => $user->_id]);
                $user->delete();
                return $view->set('message', 'Advertiser Deleted successfully!!');
            }
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

    public function register() {
        $this->seo(array("title" => "Advertiser Register", "description" => "Register"));
        $view = $this->getActionView(); $session = Registry::get("session");

        $view->set('errors', []);
        $csrf_token = $session->get('Advertiser\Register:$token');
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "register" && $csrf_token && $token === $csrf_token) {
            $this->_advertiserRegister($this->org, $view);
        }
        $csrf_token = Framework\StringMethods::uniqRandString(44);
        $session->set('Advertiser\Register:$token', $csrf_token);
        $view->set('__token', $csrf_token);
        $view->set('organization', $this->org);
    }
}