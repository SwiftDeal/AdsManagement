<?php

/**
 * The Default Example Controller Class
 *
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

    public function ad() {
        $this->willRenderLayoutView = false;
        $this->defaultExtension = "json";
        $view = $this->getActionView();
        $view->set("ad", array(
            "title" => "19 Funny Snaps That Will Make You Laugh Out Loud",
            "url" => "http://chocoapps.in/OA==",
            "image" => "http://chocoapps.in/image.php?file=56bdb51315180.jpg"
        ));
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
