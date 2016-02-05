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
     * @before _secure, _advertiser
     */
	public function index() {
		$this->seo(array("title" => "Dashboard", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
	}

	public function settings() {
		$this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
	}

	public function _advertiser() {
        $session = Registry::get("session");
        
        $advert = $session->get("advert");
        $this->_publish = $advert;

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
}