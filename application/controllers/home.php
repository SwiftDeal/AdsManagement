<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Controller as Controller;
use Framework\RequestMethods as RequestMethods;

class Home extends Controller {

    public function index() {
        $this->getLayoutView()->set("seo", Framework\Registry::get("seo"));
    }
    
    public function privacypolicy() {
        $this->seo(array("title" => "Privacy Policy", "view" => $this->getLayoutView()));
    }
    
    public function termsofservice() {
        $this->seo(array("title" => "Terms of Use", "view" => $this->getLayoutView()));
    }

    public function faqs() {
        $this->seo(array("title" => "Frequently Asked Questions", "view" => $this->getLayoutView()));
    }

    public function refundspolicy() {
        $this->seo(array("title" => "Refunds Policy", "view" => $this->getLayoutView()));
    }

    public function requestdemo() {
        $this->seo(array("title" => "Request Demo", "view" => $this->getLayoutView()));
    }

    public function contact() {
        $this->seo(array("title" => "Contact Us", "view" => $this->getLayoutView()));
    }
    
    public function seo($params = array()) {
        $seo = Framework\Registry::get("seo");
        foreach ($params as $key => $value) {
            $property = "set" . ucfirst($key);
            $seo->$property($value);
        }
        $params["view"]->set("seo", $seo);
    }
    
}
