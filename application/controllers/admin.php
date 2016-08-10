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
            'clicks' => 0, 'revenue' => 0.00
        ]);
        $networkStats = \Performance::all($query, ['clicks', 'revenue']);
        foreach ($networkStats as $p) {
            $perf->clicks += $p->clicks;
            $perf->revenue += $p->revenue;
        }

        $view->set("start", $start)
            ->set("end", $end)
            ->set("links", \Link::count(['user_id' => ['$in' => $in]]))
            ->set("platforms", \Platform::count(['user_id' => ['$in' => $in]]))
            ->set("performance", $perf);
    }

    protected function publishers() {
        $publishers = User::all(["org_id = ?" => $this->org->_id, "type = ?" => "publisher"], ["_id"]);
        $in = [];
        foreach ($publishers as $p) {
            $in[] = $p->_id;
        }
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
