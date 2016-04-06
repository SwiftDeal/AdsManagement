<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Advertiser extends Analytics {

	/**
     * @readwrite
     */
    protected $_advert;

    public function __construct($options = array()) {
        parent::__construct($options);

        $conf = Registry::get("configuration");
        $google = $conf->parse("configuration/google")->google;

        $session = Registry::get("session");
        if (!Registry::get("gClient")) {
            $client = new Google_Client();
            $client->setClientId($google->client->id);
            $client->setClientSecret($google->client->secret);
            $client->setRedirectUri('http://'.$_SERVER['HTTP_HOST'].'/advertiser/gaLogin');
            
            Registry::set("gClient", $client);
        }
    }
	
	/**
     * @before _secure, advertiserLayout
     */
	public function index() {
		$this->seo(array("title" => "Dashboard", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $database = Registry::get("database");
        $paid = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("live=?", 1)->all();
        $earn = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("live=?", 0)->all();
        $account = Account::first(array("user_id = ?" => $this->user->id), array("balance"));

        $items = Item::all(array("user_id = ?" => $this->user->id), array("id", "title", "created", "image", "url", "live", "commission"), "created", "desc", 5, 1);
        
        $view->set("items", $items);
        $view->set("account", $account);
        $view->set("paid", round($paid[0]["earn"], 2));
        $view->set("earn", round($earn[0]["earn"], 2));
	}

	/**
     * @before _secure, advertiserLayout
     */
    public function settings() {
		$this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
	}

    /**
     * @before _secure, advertiserLayout
     */
    public function billings() {
        $this->seo(array("title" => "Billings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

    /**
     * @before _secure, advertiserLayout
     */
    public function transactions() {
        $this->seo(array("title" => "Transactions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $where = array("user_id = ?" => $this->user->id);

        $transactions = Transaction::all($where);
        $count = Transaction::count($where);
        
        $view->set("transactions", $transactions);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, advertiserLayout, googleAnalytics
     */
    public function platforms() {
        $this->seo(array("title" => "Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $websites = Website::all(array("user_id = ?" => $this->user->id));

        $client = Registry::get("gClient");
        $token = $client->getAccessToken();
        if (!$token) {
            $url = $client->createAuthUrl();
            $view->set("url", $url);
        } elseif ($client->isAccessTokenExpired()) {
            $url = $client->createAuthUrl();
            $this->redirect($url);
        } elseif (RequestMethods::get("sync") == "analytics") {
            $accounts = Shared\Services\GA::fetch($client);

            $ga_stats = Registry::get("MongoDB")->ga_stats;
            foreach ($accounts as $properties) {
                foreach ($properties as $p) {
                    $website = Website::first([
                        "user_id = ?" => $this->user->id,
                        "url = ?" => $p['website']
                    ]);

                    if (!$website) {
                        $website = new Website([
                            "user_id" => $this->user->id,
                            "url" => $p['url'],
                            "gaid" => $p['id'],
                            "name" => $p['name']
                        ]);
                    }
                    $website->save();

                    foreach ($p['profiles'] as $profile) {
                        $about = $profile['about']; $cols = $profile['columns'];
                        unset($profile['about']); unset($profile['columns']);

                        foreach ($profile as $key => $value) {
                            if ($value[1] != 'Clicks99') continue;
                            $search = [
                                'source' => $value[0],
                                'medium' => $value[1],
                                'user_id' => (int) $this->user->id,
                                'website_id' => (int) $website->id
                            ];
                            $data = Shared\Services\GA::fields($value);
                            $newdata = ['$set' => array_merge($data, $search)];
                            
                            $record = $ga_stats->update([
                                $search
                            ], $newdata, [
                                'upsert' => true
                            ]);
                        }
                    }
                }
            }

            $view->set("message", "All analytics stats for Clicks99 have been stored!!");
        } else {
            $view->set("sync", true);
        }

        $view->set("websites", $websites);
    }

	public function advertiserLayout() {
        $session = Registry::get("session");
        
        $advert = $session->get("advert");
        if (isset($advert)) {
            $this->_advert = $advert;
        } else {
            $user = $this->getUser();
            if ($user) {
                $advert = Advert::first(array("user_id = ?" => $user->id), array("id"));
                if (!$advert) {
                    $this->_newAdvertiser($user);
                }
            } else {
                $this->redirect("/index.html");
            }
        }

        $this->defaultLayout = "layouts/advertiser";
        $this->setLayout();
    }

    protected function _newAdvertiser($user) {
        $advert = new Advert(array(
            "user_id" => $user->id,
            "country" => $this->country(),
            "account" => "basic"
        ));
        $advert->save();
        $session = Registry::get("session");
        $session->set("advert", $advert);
    }

    public function render() {
        if ($this->advert) {
            if ($this->actionView) {
                $this->actionView->set("advert", $this->advert);
            }

            if ($this->layoutView) {
                $this->layoutView->set("advert", $this->advert);
            }
        }    
        parent::render();
    }

    /**
     * @before _session
     */
    public function register() {
        $this->seo(array("title" => "Register as Advertiser", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::post("action") == "register") {
            $exist = User::first(array("email = ?" => RequestMethods::post("email")));
            if ($exist) {
                $view->set("message", 'User exists, <a href="/auth/login.html">login</a>');
            } else {
                $errors = $this->_advertiserRegister();
                $view->set("errors", $errors);
                if (empty($errors)) {
                    $view->set("message", "Your account has been created, we will notify you once approved.");
                }
            }
        }
    }

    /**
     * @before _secure
     */
    public function gaLogin() {
        $this->noview(); $session = Registry::get("session");
        $client = Registry::get("gClient");
        $code = RequestMethods::get("code");
        if (!$code) {
            $this->redirect("/404");
        }

        $c = $client->authenticate($code);
        $token = $client->getAccessToken();
        if (!$token) {
            $this->redirect("/404");
        }
        $session->set('Google:$accessToken', $token);
        $this->redirect("http://".$_SERVER['HTTP_HOST']."/advertiser/platforms.html");
    }

    /**
     * @protected
     */
    public function googleAnalytics() {
        $client = Registry::get("gClient"); $session = Registry::get("session");
        $token = $session->get('Google:$accessToken');
        if ($token) {
            $client->setAccessToken($token);
        }

        $client->setApplicationName("Cloudstuff");
        $client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);

        Registry::set("gClient", $client);
    }
}
