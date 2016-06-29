<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use WebBot\Core\Bot as Bot;

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

    public function timeline() {
        $this->seo(array("title" => "Timeline of awesomeness", "view" => $this->getLayoutView()));
    }

    public function team() {
        $this->seo(array("title" => "Team behind vNative", "view" => $this->getLayoutView()));
    }

    public function pricing() {
        $this->seo(array("title" => "vNative pricing", "view" => $this->getLayoutView()));
    }

    public function refundspolicy() {
        $this->seo(array("title" => "Refunds Policy", "view" => $this->getLayoutView()));
    }

    public function requestdemo() {
        $this->seo(array("title" => "Request Demo", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if(RequestMethods::post('name')){
            $enquiry = new Enquiry(array(
                'name' => RequestMethods::post('name'),
                'c_website' => RequestMethods::post('url'),
                'phone' => RequestMethods::post('phone'),
                'email' => RequestMethods::post('email'),
                'message' => RequestMethods::post('message', ''),
                'type' => 2
             ));

            if($enquiry->validate()){
                $enquiry->save();
                $view->set("message", 'Request Submitted Successfully!!!');
                $this->notify(array(
                    "template" => "requestDemo",
                    "subject" => "Response: Demo Request",
                    "user" => $enquiry
                ));
            }else{
                $enquiry->getErrors();
            }
        }
    }

    public function contact() {
        $this->seo(array("title" => "Contact Us", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
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
                $view->set("message", 'Request Submitted Successfully!!!');
            }else{
                $enquiry->getErrors();
            }
        }
    }

    public function livedemo() {
        if (RequestMethods::get("link")) {
            $this->willRenderLayoutView = false;
            $this->willRenderActionView = true;
            $view = $this->getActionView();
            $view->set("link", RequestMethods::get("link"));

            if (!Cookie\Cookie::Exists("vtarck")) {
                $cookie = uniqid();
                Cookie\Cookie::Set('vtarck', $cookie, Cookie\Cookie::Lifetime, '/', '.vnative.com');

                $demo = new \Demo(array(
                    "url" => RequestMethods::get("link"),
                    "ip" => $this->get_client_ip(),
                    "cookie" => $cookie
                ));
                $demo->save();
            }

            WebBot\Core\Bot::$logging = false;
            $bot = new Bot(array(
                'url' => RequestMethods::get("link")
            ));

            // execute
            $bot->execute();

            $documents = $bot->getDocuments();
            foreach ($documents as $doc) {
                $body = $doc->getBody(); // will return whole html as string

                $xmlPageDom = new \DomDocument(); // Instantiating a new DomDocument object
                @$xmlPageDom->loadHTML($body);
                //$xmlPageDom->createElement('script', 'alert("HI")');
                echo $xmlPageDom->saveHTML();
            }
        } else {
            $this->seo(array("title" => "Live Demo", "view" => $this->getLayoutView()));
        }
    }

    protected function get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        $ip = explode(",", $ipaddress);
        return $ip[0];
    }
}
