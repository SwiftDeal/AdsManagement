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
	}

	public function settings() {
		$this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
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

            } else {
                self::redirect("/index.html");
            }
        }

        $this->defaultLayout = "layouts/advertiser";
        $this->setLayout();
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