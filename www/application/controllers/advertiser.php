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

    protected function setAdvert($advert) {
        $session = Registry::get("session");
        if ($advert) {
            $session->set("advert", $advert);
        } else {
            $session->erase("advert");
        }
        $this->_advert = $advert;
        return $this;
    }

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
        $view = $this->getActionView(); $client = Registry::get("gClient");

        $access = Access::all(array("user_id = ?" => $this->user->id, "property = ?" => "website"));
        $advert = Advert::first(["user_id = ?" => $this->user->id]); $this->setAdvert($advert);
        $token = $this->advert->gatoken;
        
        if (!$token && !$access) {
            $url = $client->createAuthUrl();
            $view->set("url", $url);
        } elseif ($token && !$access) {
            $client->refreshToken($token);
            $msg = "All analytics stats for Clicks99 have been stored!!";
            try {
                Shared\Services\GA::update($client, $this->user); 
            } catch (\Exception $e) {
                $msg = $e->getMessage();
            }
            $access = Access::all(array("user_id = ?" => $this->user->id, "property = ?" => "website"));
            
            $view->set("message", $msg);
        }

        $view->set("access", $access);
    }

    /**
     * @before _secure, advertiserLayout, googleAnalytics
     */
    public function stats() {
        $this->seo(array("title" => "Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $insights = Insight::all(array("user_id = ?" => $this->user->id));
        $view->set("insights", $insights);
    }

    /**
     * @protected
     */
    public function advertiserLayout() {
        $session = Registry::get("session");
        
        $advert = $session->get("advert");
        if (isset($advert)) {
            $this->_advert = $advert;
        } else {
            $user = $this->getUser();
            $advert = Advert::first(array("user_id = ?" => $user->id), array("id"));
            if ($user && $advert) {
                $session->set("advert", $advert);
            } else {
                $this->redirect("/index.html");
            }
        }

        $this->defaultLayout = "layouts/advertiser";
        $this->setLayout();
    }

    /**
     * @protected
     */
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
        $refreshToken = (isset($token["refresh_token"]) ? $token["refresh_token"] : null);
        if ($refreshToken) {
            $advert = Advert::first(["user_id = ?" => $this->user->id]);
            if (!$advert) {
                $advert = new Advert([
                    "user_id" => $this->user->id,
                    "live" => true,
                    "country" => "IN"
                ]);
            }
            $advert->gatoken = $refreshToken;
            $advert->save();
        }
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
        $client->setAccessType("offline");

        Registry::set("gClient", $client);
    }
}
