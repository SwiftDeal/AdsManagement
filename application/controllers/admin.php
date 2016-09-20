<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Admin extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard"));
        $view = $this->getActionView(); $d = 1;

        $publishers = User::all([
            "org_id = ?" => $this->org->_id, "type = ?" => "publisher"
        ], ["_id"]); $in = [];
        foreach ($publishers as $p) {
            $in[] = $p->_id;
        }

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-4 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [
            'user_id' => ['$in' => $in],
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];
        
        $perf = new Performance([
            'clicks' => 0, 'revenue' => 0.00, 'impressions' => 0
        ]);
        $networkStats = \Performance::all($query, ['clicks', 'revenue', 'impressions']);
        foreach ($networkStats as $p) {
            $perf->clicks += $p->clicks;
            $perf->impressions += $p->impressions;
            $perf->revenue += $p->revenue;
        }
        $topusers = $this->widgets($dateQuery);
        if (array_key_exists("widgets", $this->org->meta)) {
            $d = in_array("top10ads", $this->org->meta["widgets"]) + in_array("top10pubs", $this->org->meta["widgets"]);
        }
        $view->set("start", $start)
            ->set("end", $end)
            ->set("d", 12/$d)
            ->set("topusers", $topusers)
            ->set("links", \Link::count(['user_id' => ['$in' => $in]]))
            ->set("platforms", \Platform::count(['user_id' => ['$in' => $in]]))
            ->set("performance", $perf);
    }

    protected function orgusers($type = "publisher") {
        $publishers = User::all(["org_id = ?" => $this->org->_id, "type = ?" => $type], ["_id"]);
        $in = [];
        foreach ($publishers as $p) {
            $in[] = Utils::mongoObjectId($p->_id);
        }
        return $in;
    }

    /**
     * @before _secure
     */
    public function account() {
    	$this->seo(array("title" => "Account Settings"));
    	$view = $this->getActionView();

    	$user = $this->user; $org = $this->org;

    	$view->set("errors", []);
    	if (RequestMethods::type() == 'POST') {
    		$action = RequestMethods::post('action', '');
    		switch ($action) {
    			case 'account':
    				$name = RequestMethods::post('name');
    				$currency = RequestMethods::post('currency', 'INR');

    				$user->name = $name; $user->currency = $currency;

    				$user->save();
    				$view->set('message', 'Account Updated!!');
    				break;

    			case 'password':
    				$old = RequestMethods::post('password');
    				$new = RequestMethods::post('npassword');
    				$view->set($user->updatePassword($old, $new));
    				break;

                case 'org':
                    if (RequestMethods::post("widgets")) {
                        $meta = $org->meta;
                        $meta["widgets"] = RequestMethods::post("widgets");
                        $org->meta = $meta;
                    }
                    if (RequestMethods::post("zopim")) {
                        echo "here";
                        $meta = $org->meta;
                        $meta["zopim"] = RequestMethods::post("zopim");
                        $org->meta = $meta;
                    }
                    $org->url = RequestMethods::post('url');
                    $org->email = RequestMethods::post('email');
                    $org->save(); $this->setOrg($org);
                    $view->set('message', 'Network Settings updated!!');
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
    public function settings() {
        $this->seo(array("title" => "Settings"));
        $view = $this->getActionView();

        $user = $this->user; $org = $this->org;
        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'commission':
                    $meta = [
                        'model' => RequestMethods::post('model'),
                        'rate' => $this->currency(RequestMethods::post('rate'))
                    ];
                    $org->meta = $meta;
                    $org->save();
                    $view->set('message', 'Commission Settings updated!!');
                    break;

                case 'domains':
                    $message = $org->updateDomains();
                    $this->setOrg($org);
                    $view->set('message', $message);
                    break;

                case 'categories':
                    $msg = Category::updateNow($this->org);
                    $view->set('message', $msg);
                    break;
            }
            $this->setUser($user);
        }
        $categories = \Category::all(['org_id' => $this->org->_id]);
        $view->set('categories', $categories);
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
        
        $find = Performance::overall($dateQuery, User::all(["type" => "publisher", "org_id = ?" => $this->org->id], ["_id"]));
        $view->set($find);
    }

    /**
     * @before _secure
     */
    public function notification() {
        $this->seo(array("title" => "Notification"));
        $view = $this->getActionView();

        switch (RequestMethods::post("action")) {
            case 'save':
                $n = new Notification([
                    "org_id" => $this->org->id,
                    "message" => RequestMethods::post("message"),
                    "target" => RequestMethods::post("target")
                ]);
                $n->save();
                $view->set("message", "Saved Successfully");
                break;
        }

        switch (RequestMethods::get("action")) {
            case 'delete':
                $id = RequestMethods::get("id");
                $n = Notification::first(["org_id = ?" => $this->org->id, "id = ?" => $id]);
                if ($n) {
                    $n->delete();
                    $view->set("message", "Deleted Successfully");
                } else {
                    $view->set("message", "Notification does not exist");
                }
                break;
        }

        $notifications = Notification::all(["org_id = ?" => $this->org->id], [], "created", "desc");
        $view->set("notifications", $notifications);
    }

    /**
     * @before _secure
     */
    public function billing() {
        $this->seo(array("title" => "Billing")); $view = $this->getActionView();
        $start = RequestMethods::get('start', date('Y-m-d', strtotime('-30 day')));
        $end = RequestMethods::get('end', date('Y-m-d'));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        // find advertiser performances to get clicks and impressions
        $performances = \Performance::overall(
            $dateQuery,
            User::all(['org_id' => $this->org->_id, 'type' => 'advertiser'], ['_id'])
        );

        $clicks = $performances['total_clicks'];
        if ($clicks < 100000) {
            $price = 15 / 1000;
        } else {
            $price = 10 / 1000;
        }
        $click_cost = $price * $clicks;

        $impressions = $performances['total_impressions'];
        if ($impressions < 100000) {
            $price = 15 / 1000; // it's in INR
        } else {
            $price = 10 / 1000;
        }
        $imp_cost = $impressions * $price;

        $view->set([
            'start' => $start,
            'end' => $end,
            'clicks' => [ 'total' => $clicks, 'cost' => $click_cost ],
            'impressions' => [ 'total' => $impressions, 'cost' => $imp_cost ]
        ]);
    }

    /**
     * @before _secure
     */
    public function platforms() {
        $this->seo(array("title" => "Platforms")); $view = $this->getActionView();

        $query['user_id'] = ['$in' => $this->orgusers()];
        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);
        $property = RequestMethods::get("property");
        $value = RequestMethods::get("value");
        if (in_array($property, ["live", "url", "user_id"])) {
            $query["{$property} = ?"] = $value;
        }

        $platforms = Platform::all($query, [], 'created', 'desc', $limit, $page);
        $count = Platform::count($query);

        $view->set("platforms", $platforms)
            ->set("count", $count)
            ->set("property", $property)
            ->set("value", $value)
            ->set("limit", $limit)
            ->set("page", $page);
    }

    /**
     * @before _secure
     */
    public function newTrans($user_id) {
        $user = \User::first(['org_id' => $this->org->_id, '_id' => $user_id]);
        if (!$user) $this->_404();
        $this->seo(array("title" => "New Transaction for User: " . $user->name)); $view = $this->getActionView();

        $transaction = \Transaction::first(['user_id' => $user->_id], [], 'created', 'desc');
        $dateQuery = [];
        if ($transaction) {
            $dateQuery['start'] = $transaction->created;
            $dateQuery['end'] = new \MongoDB\BSON\UTCDateTime(strtotime('now') * 1000);
        }
        $perf = \Performance::overall($dateQuery, $user);

        $view->set([ 'errors' => [], 'usr' => $user, 'payment' => $perf['total_payouts'] ]);
        if (RequestMethods::type() === 'POST') {
            $trans = new \Transaction([
                'org_id' => $this->org->_id,
                'user_id' => $user->_id,
                'amount' => $this->currency(RequestMethods::post('amount')),
                'ref' => RequestMethods::post('ref')
            ]);
            if ($trans->validate()) {
                $trans->save();
                $view->set('message', 'Transaction Added!! for user');
            } else {
                $view->set('errors', $trans->errors);
            }
        }
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->_404();
        }
        $this->setLayout("layouts/admin");
    }
}
