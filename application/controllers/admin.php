<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RM;
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
        
        $start = RM::get("start", date('Y-m-d', strtotime('-4 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('now')));
        
        $dq = [
            'start' => date('Y-m-d', strtotime("-365 day")), 'end' => $end
        ];
        $pubPerf = (object) Performance::total($dq, $publishers);
        $advPerf = (object) Performance::total($dq, $this->org->users('advertiser', 'users'), ['revenue']);

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
            ->set("performance", Performance::calProfit($pubPerf, $advPerf));
    }

    /**
     * @before _secure
     * @after _csrfToken
     */
    public function settings() {
        $this->seo(array("title" => "Settings")); $view = $this->getActionView();
        $user = $this->user; $org = $this->org;

        $search = ['prop' => 'customField', 'propid' => $org->_id];
        $meta = Meta::first($search) ?? (object) [];
        $view->set('fields', $meta->value ?? []);

        $apikey = ApiKey::first(["org_id = ?" => $org->id]);
        $mailConf = Meta::first(['prop' => 'orgSmtp', 'propid' => $this->org->_id]) ?? (object) [];
        $view->set('mailConf', $mailConf->value ?? [])
            ->set("errors", []);
        
        if (RM::type() == 'POST') {
            $action = RM::post('action', '');
            switch ($action) {
                case 'account':
                    $user->name = RM::post('name');
                    $user->currency = RM::post('currency', 'INR');
                    $user->region = [
                        "currency" => RM::post('currency', 'INR'),
                        "zone" => RM::post('timezone', 'Asia/Kolkata')
                    ];
                    $user->phone = RM::post('phone');

                    $user->save();
                    $view->set('message', 'Account Updated!!');
                    break;

                case 'password':
                    $old = RM::post('password');
                    $new = RM::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'billing':
                    $billing = $org->billing;
                    $billing["aff"]["auto"] = RM::post("autoinvoice", 0);
                    $billing["aff"]["freq"] = RM::post("freq", 15);
                    $billing["aff"]["minpay"] = $this->currency(RM::post('minpay', 100));
                    $billing["aff"]["ptypes"] = RM::post("ptypes");
                    $billing["adv"]["paypal"] = RM::post("paypal");
                    $org->billing = $billing;
                    $org->save(); $this->setOrg($org);
                    $view->set('message', 'Organization Billing Updated!!');
                    break;

                case 'org':
                    $meta = $org->meta;
                    if (RM::post("widgets")) {    
                        $meta["widgets"] = RM::post("widgets");
                        $org->meta = $meta;
                    }
                    $zopim = RM::post("zopim");
                    $meta["zopim"] = $zopim;
                    if (strlen($zopim) == 0) {
                        unset($meta["zopim"]);
                    }
                    $org->name = RM::post('name');
                    $org->meta = $meta;
                    $org->logo = $this->_upload('logo');
                    $org->url = RM::post('url');
                    $org->email = RM::post('email');
                    $org->save(); $this->setOrg($org);
                    $view->set('message', 'Network Settings updated!!');
                    break;
                
                case 'customField':
                    $label = RM::post("fname");
                    $type = RM::post("ftype", "text");
                    $required = RM::post("frequired", 1);
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
        $view->set("apiKey", $apikey);

        if (RM::type() === 'DELETE') {
            if (is_a($meta, 'Meta')) {
                $meta->delete();
            }
            $view->set('message', 'Extra Fields removed!!');
        }
        
        $img = RM::get("img");
        if (RM::get("action") == "removelogo" && $img === $org->logo) {
            Utils::media($org->logo, 'remove');
            $org->logo = ' '; $this->setOrg($org);
            $org->save();
            $this->redirect("/admin/settings.html");
        }
    }

    /**
     * Returns data of clicks, impressions, payouts for publishers with custom date range
     * @before _secure
     */
    public function performance() {
        $this->JSONview();$view = $this->getActionView();
        
        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));
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

        switch (RM::post("action")) {
            case 'save':
                $meta = RM::post("meta", "all");
                $message = RM::post("message");
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
                    "target" => RM::post("target"),
                    "meta" => $meta
                ]);
                $n->save();
                $view->set("message", $success);
                break;
        }

        if (RM::type() === 'DELETE') {
            $id = RM::get("id");
            $n = Notification::first(["org_id = ?" => $this->org->id, "id = ?" => $id]);
            if ($n) {
                $n->delete();
                $view->set("message", "Deleted Successfully");
            } else {
                $view->set("message", "Notification does not exist");
            }
        }

        $notifications = Notification::all(["org_id = ?" => $this->org->id], [], "created", "desc");
        $view->set("notifications", $notifications);
    }

    /**
     * @before _secure
     */
    public function billing() {
        $this->seo(array("title" => "Billing")); $view = $this->getActionView();
        $bills = Bill::all(["org_id = ?" => $this->org->id], ['id', 'created'], "created", "desc");
        $invoice = RM::get("invoice", "current");
        $imp_cost = 0; $click_cost = 0;
        switch ($invoice) {
            case 'current':
                $start = RM::get('start', date('Y-m-01'));
                $end = RM::get('end', date('Y-m-d'));
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
                $start = $bill->start; $end = $bill->end;
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
            'start' => $start, 'end' => $end, 'invoice' => $invoice,
            'impressions' => [ 'total' => $impressions, 'cost' => $imp_cost ]
        ]);
    }

    /**
     * @before _secure
     */
    public function platforms($id = null) {
        $this->seo(array("title" => "Platforms")); $view = $this->getActionView();

        $query['user_id'] = ['$in' => $this->org->users('publisher')];
        $limit = RM::get("limit", 20);
        $page = RM::get("page", 1);
        $property = RM::get("property", '');
        $value = RM::get("value");
        
        if (in_array($property, ["live", "user_id"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["url"])) {
            $query[$property] = Utils::mongoRegex($value);
        }

        if (RM::type() === 'POST') {
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

        if (RM::type() === 'DELETE') {
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
     * @before _admin
     */
    public function postbacks() {
        $this->seo(array("title" => "Network: PostBacks"));
        $view = $this->getActionView();

        $postbacks = \PostBack::all(['org_id = ?' => $this->org->id]);

        $view->set('postbacks', $postbacks);
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
