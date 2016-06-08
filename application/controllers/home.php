<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;

class Home extends Auth {

    public function index() {
        $this->getLayoutView()->set("seo", Framework\Registry::get("seo"));
    }
    
    public function privacypolicy() {
        $this->seo(array("title" => "Privacy Policy", "view" => $this->getLayoutView()));
    }
    
    public function termsofservice() {
        $this->seo(array("title" => "Terms of Use", "view" => $this->getLayoutView()));
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

    public function livedemo() {
        $this->seo(array("title" => "Live Demo", "view" => $this->getLayoutView()));
    }
}
