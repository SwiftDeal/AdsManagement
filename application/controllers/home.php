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
    
    public function privacy() {
        $this->seo(array(
            "title" => "Privacy Policy",
            "view" => $this->getLayoutView()
        ));
    }
    
    public function termsofuse() {
        $this->seo(array(
            "title" => "Terms of Use",
            "view" => $this->getLayoutView()
        ));
    }

    public function contact() {
        $this->seo(array(
            "title" => "Contact Us",
            "view" => $this->getLayoutView()
        ));

        if (RequestMethods::post("message")) {
            $emails = array();
            array_push($emails, RequestMethods::post("email"));
            $options = array(
                "template" => "blank",
                "subject" => RequestMethods::post("subject"),
                "message" => RequestMethods::post("message"),
                "emails" => $emails,
                "delivery" => "mailgun"
            );
            $this->notify($options);
            $view->set("success", TRUE);
        }
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
    
}
