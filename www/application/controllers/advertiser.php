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
        $where = array(
            "live = ?" => true,
            "user_id = ?" => $this->user->id
        );

        $items = Item::all($where, array("id", "title", "created", "image", "url", "live", "commission"), "created", "desc", 5, 1);
        $view->set("items", $items);
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