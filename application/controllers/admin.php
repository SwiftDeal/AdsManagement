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
        $view = $this->getActionView();

        $publishers = User::all(["org_id = ?" => $this->org->_id, "type = ?" => "publisher"], ["_id"]);
        $in = [];
        foreach ($publishers as $p) {
            $in[] = $p->_id;
        }

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-4 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [
            'pid' => ['$in' => $in],
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];
        
        $clickCol = Registry::get("MongoDB")->clicks;
        $clicks = $clickCol->find($query,['adid', 'cookie', 'ipaddr', 'referer']);

        $view->set("start", $start)
            ->set("end", $end)
            ->set("performance", $this->perf($clicks, $this->user));
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
                    $org->url = RequestMethods::post('url');
                    $org->save();
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
                        'model' => RequestMethods::post('model', 'cpc'),
                        'rate' => RequestMethods::post('rate', round(0.25 / 66.76, 6))
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
        
        $find = Performance::overall($dateQuery, User::all(["org_id = ?" => $this->org->id], ["_id"]));
        $view->set($find);
    }

    /**
     * @before _secure
     */
    public function notification() {
        $this->seo(array("title" => "Notification"));
        $view = $this->getActionView();
        
        $notifications = Notification::all(["org_id = ?" => $this->org->id], [], "created", "desc", 10, 1);
        $view->set("notifications", $notifications);

        if (RequestMethods::post("action") == "save") {
            $n = new Notification([
                "org_id" => $this->org->id,
                "message" => RequestMethods::post("message"),
                "target" => RequestMethods::post("target")
            ]);
            $n->save();
            $view->set("message", "Saved Successfully");
        }
    }

    /**
     * @before _secure
     */
    public function billing() {
        $this->seo(array("title" => "Billing"));
        $view = $this->getActionView();
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/admin");
    }
}
