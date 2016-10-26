<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;
use Shared\Services\Db as Db;

class Admin extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard"));
        $view = $this->getActionView(); $d = 1;

        $publishers = User::all([
            "org_id = ?" => $this->org->_id, "type = ?" => "publisher"
        ], ["_id"]);
        $in = array_keys($publishers);
        
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-4 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        
        $data = Shared\Services\Performance::stats($this->org, ['start' => $start, 'end' => $end]);
        $topusers = $this->widgets();
        if (array_key_exists("widgets", $this->org->meta)) {
            $d = in_array("top10ads", $this->org->meta["widgets"]) + in_array("top10pubs", $this->org->meta["widgets"]);
        }
        $view->set("start", $start)
            ->set("end", $end)
            ->set("d", 12/$d)
            ->set("topusers", $topusers)
            ->set("links", \Link::count(['user_id' => ['$in' => $in]]))
            ->set("platforms", \Platform::count(['user_id' => ['$in' => $in]]))
            ->set("performance", (object) $data['total']);
    }

    /**
     * @before _secure
     * @after _csrfToken
     */
    public function customization() {
        $this->seo(array("title" => "Account Settings"));
        $view = $this->getActionView();

        $user = $this->user; $org = $this->org;
        $search = ['prop' => 'customField', 'propid' => $org->_id];
        $meta = Meta::first($search) ?? (object) [];
        $view->set('fields', $meta->value ?? []);

        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $user->name = RequestMethods::post('name');
                    $user->currency = RequestMethods::post('currency', 'INR');
                    $user->phone = RequestMethods::post('phone');

                    $user->save();
                    $view->set('message', 'Account Updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'billing':
                    $billing = $org->billing;
                    $billing["aff"]["auto"] = RequestMethods::post("autoinvoice", false);
                    $billing["aff"]["freq"] = RequestMethods::post("freq", 15);
                    $billing["aff"]["minpay"] = $this->currency(RequestMethods::post('minpay', 100));
                    $billing["aff"]["ptypes"] = RequestMethods::post("ptypes");
                    $billing["adv"]["paypal"] = RequestMethods::post("paypal");
                    $org->billing = $billing;
                    $org->save(); $this->setOrg($org);
                    $view->set('message', 'Organization Billing Updated!!');
                    break;

                case 'org':
                    $meta = $org->meta;
                    if (RequestMethods::post("widgets")) {    
                        $meta["widgets"] = RequestMethods::post("widgets");
                        $org->meta = $meta;
                    }
                    $zopim = RequestMethods::post("zopim");
                    $meta["zopim"] = $zopim;
                    if (strlen($zopim) == 0) {
                        unset($meta["zopim"]);
                    }
                    $org->name = RequestMethods::post('name');
                    $org->meta = $meta;
                    $org->logo = $this->_upload('logo');
                    $org->url = RequestMethods::post('url');
                    $org->email = RequestMethods::post('email');
                    $org->save(); $this->setOrg($org);
                    $view->set('message', 'Network Settings updated!!');
                    break;
                
                case 'customField':
                    $label = RequestMethods::post("fname");
                    $type = RequestMethods::post("ftype", "text");
                    $required = RequestMethods::post("frequired", 1);
                    $name = strtolower(str_replace(" ", "_", $label));

                    $field = [
                        'label' => ucwords($label), 'type' => $type,
                        'name' => $name, 'required' => (boolean) $required
                    ];

                    if (!$label) break;
                    if (!is_object($meta) || !is_a($meta, 'Meta')) {
                        $meta = new Meta($search);
                    }

                    $fields = $meta->value; $fields[] = $field;
                    $meta->value = $fields; $meta->save();
                    $view->set('fields', $meta->value ?? []);
                    $view->set('message', 'Extra Field Added!!');
                    break;
            }
            $this->setUser($user);
        }

        if (RequestMethods::type() === 'DELETE') {
            if (is_a($meta, 'Meta')) {
                $meta->delete();
            }
            $view->set('message', 'Extra Fields removed!!');
        }
        
        $img = RequestMethods::get("img");
        if (RequestMethods::get("action") == "removelogo" && $img === $org->logo) {
            @unlink(APP_PATH . '/public/assets/uploads/images/' . $org->logo);
            $org->logo = null; $this->setOrg($org);
            Db::updateRaw('organizations', ['_id' => Db::convertType($org->_id, 'id')], ['$unset' => ['logo' => 1]]);
            $this->redirect("/admin/customization.html");
        }
    }

    /**
     * @before _secure
     */
    public function settings() {
        $this->seo(array("title" => "Settings"));
        $view = $this->getActionView();

        $user = $this->user; $org = $this->org;
        $apikey = ApiKey::first(["org_id = ?" => $org->id]);
        $mailConf = Meta::first(['prop' => 'orgSmtp', 'propid' => $this->org->_id]) ?? (object) [];
        $view->set('mailConf', $mailConf->value ?? [])
            ->set("errors", []);
        
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'commission':
                    $view->set('message', 'Commission updated!!');
                    break;

                case 'commadd':
                    $this->addCommisson($org);
                    break;

                case 'domains':
                    $message = $org->updateDomains();
                    $this->setOrg($org);
                    $view->set('message', $message);
                    break;

                case 'categories':
                    $success = Category::updateNow($this->org);
                    if ($success) {
                        $msg = 'Categories updated Successfully!!';
                    } else {
                        $msg = 'Failed to delete some categories because in use by campaigns!!';
                    }
                    $view->set('message', $msg);
                    break;

                case 'smtp':
                    $msg = \Shared\Services\Smtp::create($this->org);
                    $view->set('message', $msg);
                    break;

                case 'apikey':
                    $view->set('message', "Api Key Updated!!");
                    if (!$apikey) {
                        $apikey = new ApiKey([
                            'org_id' => $this->org->_id,
                            'key' => uniqid() . uniqid() . uniqid()
                        ]);
                        $view->set('message', "Api Key Created!!");
                    }
                    $apikey->updateIps();
                    $apikey->save();
                    break;
            }
            $this->setUser($user);
        }
        $categories = \Category::all(['org_id' => $this->org->_id]);
        $view->set('categories', $categories)
            ->set("apiKey", $apikey);
    }

    protected function addCommisson($org) {
        $meta = $org->meta;
        if (array_key_exists('commission', $meta)) {
            $arr = $meta["commission"];
            array_push($arr, [
                'coverage' => RequestMethods::post('coverage', ['ALL']),
                'model' => RequestMethods::post('model'),
                'rate' => $this->currency(RequestMethods::post('rate'))
            ]);
            $meta["commission"] = $arr;
        } else {
            $arr = [];
            $arr[] = [
                'coverage' => RequestMethods::post('coverage', ['ALL']),
                'model' => RequestMethods::post('model'),
                'rate' => $this->currency(RequestMethods::post('rate'))
            ];
            $meta["commission"] = $arr;
        }
        
        $org->meta = $meta;
        $org->save();
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
        $view = $this->getActionView(); $fields = ['_id', 'name'];
        
        $arr = User::all(['org_id' => $this->org->_id, 'type' => 'publisher'], $fields);
        $publishers = User::objectArr($arr, $fields);
        $arr = User::all(['org_id' => $this->org->_id, 'type' => 'advertiser'], $fields);
        $advertisers = User::objectArr($arr, $fields);

        $view->set('publishers', $publishers)
            ->set('advertisers', $advertisers);

        switch (RequestMethods::post("action")) {
            case 'save':
                $meta = RequestMethods::post("meta");
                $message = RequestMethods::post("message");
                $success = "Saved Successfully";

                if ($meta !== "all" && (
                    !in_array($meta, array_keys($publishers)) &&
                    !in_array($meta, array_keys($advertisers))
                )) {
                    $view->set('message', "Invalid Request!!");
                    break;
                } else if ($meta !== "all") {
                    // send mail to the user
                    $usr = User::first(['_id' => $meta], ['name', 'email']);
                    \Shared\Services\Smtp::sendMail($this->org, [
                        'template' => 'notification',
                        'user' => $usr,
                        'notification' => $message,
                        'to' => [$usr->email],  // this argument expects array value
                        'subject' => "Notification from " . $this->org->name
                    ]);
                    $success .= " And Mail sent";
                }
                $n = new Notification([
                    "org_id" => $this->org->id,
                    "message" => $message,
                    "target" => RequestMethods::post("target"),
                    "meta" => $meta
                ]);
                $n->save();
                $view->set("message", $success);
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
        $bills = Bill::all(["org_id = ?" => $this->org->id]);
        $invoice = RequestMethods::get("invoice", "current");
        $imp_cost = 0; $click_cost = 0;
        switch ($invoice) {
            case 'current':
                $start = RequestMethods::get('start', date('Y-m-01'));
                $end = RequestMethods::get('end', date('Y-m-d'));
                $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

                // find advertiser performances to get clicks and impressions
                $performances = \Performance::overall(
                    $dateQuery,
                    User::all(['org_id' => $this->org->_id, 'type' => 'advertiser'], ['_id'])
                );
                $clicks = $performances['total_clicks'];
                $impressions = $performances['total_impressions'];
                break;
            
            default:
                $bill = Bill::first(["org_id = ?" => $this->org->id, "id = ?" => $invoice]);
                $clicks = $bill->clicks;
                $impressions = $bill->impressions;
                break;
        }
        if ($clicks > 1000) {
            $click_cost = 0.001*$clicks*$this->org->meta["bill"]["tcc"];
        }
        if ($impressions > 1000000) {
            $imp_cost = 0.001*0.001*$impressions*$this->org->meta["bill"]["mic"];
        }
        $view->set([
            'bills' => $bills,
            'clicks' => [ 'total' => $clicks, 'cost' => $click_cost ],
            'impressions' => [ 'total' => $impressions, 'cost' => $imp_cost ]
        ]);
    }

    /**
     * @before _secure
     */
    public function platforms($id = null) {
        $this->seo(array("title" => "Platforms")); $view = $this->getActionView();

        $query['user_id'] = ['$in' => $this->org->users('publisher')];
        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);
        $property = RequestMethods::get("property", '');
        $value = RequestMethods::get("value");
        
        if (in_array($property, ["live", "user_id"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["url"])) {
            $query[$property] = Utils::mongoRegex($value);
        }

        if (RequestMethods::type() === 'POST') {
            $p = \Platform::first(['_id' => $id, 'user_id' => $query['user_id']]);
            if (!$p) {
                return $view->set('message', "Invalid Request!!");
            }

            try {
                $updateAble = ['live', 'user_id', 'url'];
                foreach ($_POST as $key => $value) {
                    if (in_array($key, $updateAble)) {
                        $p->$key = $value;
                    }
                }
                $p->save();

                return $view->set('message', 'Platform updated!!');
            } catch (\Exception $e) {
                return $view->set('message', "Invalid Request Parameters!!");
            }
        }

        if (RequestMethods::type() === 'DELETE') {
            $p = \Platform::first(['_id' => $id, 'user_id' => $query['user_id']]);
            if (!$p) {
                return $view->set('message', "Invalid Request!!");
            }
            $p->delete();
            return $view->set('message', "Platform Removed!!");
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
            $lastCreated = $transaction->created->format('Y-m-d');
            $dateQuery = [
                'start' => Db::time($lastCreated),
                'end' => Db::time()
            ];
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
