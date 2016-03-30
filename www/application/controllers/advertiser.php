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
     * @before _secure, advertiserLayout
     */
    public function platforms() {
        $this->seo(array("title" => "Transactions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
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
}