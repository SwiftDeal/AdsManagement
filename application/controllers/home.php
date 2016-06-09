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
 
        if(RequestMethods::post('name')){

            $enquiry = new Enquiry(array(
                'name' => RequestMethods::post('name'),
                'c_website' => RequestMethods::post('url'),
                'phone' => RequestMethods::post('phone'),
                'email' => RequestMethods::post('email'),
                'message' => RequestMethods::post('message'),
                'type' => 2
                ));

            if($enquiry->validate()){

                $enquiry->save();
            }else{
                $enquiry->getErrors();
            }

        }
    }

    public function contact() {
        $this->seo(array("title" => "Contact Us", "view" => $this->getLayoutView()));

        if(RequestMethods::post('name')){

            $enquiry = new Enquiry(array(
                'name' => RequestMethods::post('name'),
                'phone' => RequestMethods::post('phone'),
                'email' => RequestMethods::post('email'),
                'message' => RequestMethods::post('message'),
                'type' => 1
                ));

            if($enquiry->validate()){

                $enquiry->save();
            }else{
                $enquiry->getErrors();
            }

        }
        
    }


    public function livedemo() {
        $this->seo(array("title" => "Live Demo", "view" => $this->getLayoutView()));
    }
}
