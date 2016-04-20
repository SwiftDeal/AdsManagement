<?php
/**
 * Description of auth
 *
 * @author Faizan Ayubi
 */
use Shared\Controller as Controller;
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use \Curl\Curl;

class Auth extends Controller {
    
    /**
     * @before _session
     */
    public function login() {
        $this->seo(array("title" => "Login", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $fb = RequestMethods::get("fb", false);
        if (RequestMethods::post("action") == "login") {
            $message =  $this->_login();
            $view->set("message", $message);
        }
        $view->set("fb", $fb);
    }

    /**
     * @before _session
     */
    public function forgotpassword() {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "reset" && $this->reCaptcha()) {
            $message = $this->_resetPassword();
            $view->set("message", $message);
        }
    }

    /**
     * @before _session
     */
    public function resetpassword($token) {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $meta = Meta::first(array("value = ?" => $token, "property = ?" => "resetpass"));
        if (!isset($meta)) {
            $this->redirect("/index.html");
        }

        if (RequestMethods::post("action") == "change" && $this->reCaptcha()) {
            $user = User::first(array("id = ?" => $meta->user_id));
            if(RequestMethods::post("password") == RequestMethods::post("cpassword")) {
                $user->password = sha1(RequestMethods::post("password"));
                $user->save();
                $meta->delete();
                $view->set("message", 'Password changed successfully now <a href="/login.html">Login</a>');
            } else{
                $view->set("message", 'Password Does not match');
            }
        }
    }

    protected function _login() {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if($exist) {
            if($exist->password == sha1(RequestMethods::post("password"))) {
                if ($exist->live) {
                    return $this->authorize($exist);
                } else {
                    return "User account not verified";
                }
            } else{
                return 'Wrong Password, Try again or <a href="/auth/forgotpassword.html">Reset Password</a>';
            }
            
        } else {
            return 'User doesnot exist. Please signup <a href="/publisher/register.html">here</a>';
        }
    }

    protected function authorize($user) {
        $session = Registry::get("session");
        //setting team
        $team = Team::first(array("user_id = ?" => $user->id));
        if ($team) {
            if ($team->live == 0) {
                return "Account Suspended";
            }
            $this->setUser($user);
            $session->set("team", $team);
            $this->redirect("/manage/index.html");
        }

        //setting publisher
        $publish = Publish::first(array("user_id = ?" => $user->id));
        if ($publish) {
            if ($publish->live == 0) {
                return "Account Suspended";
            }
            $this->setUser($user);
            //setting domains
            $domains = Meta::all(array("property = ?" => "domain", "live = ?" => true));
            $session->set("domains", $domains);
            $session->set("publish", $publish);
            if (RequestMethods::post("action") == "fblogin") {
                return true;
            } else {
                $this->redirect("/publisher/index.html");
            }
        }

        //setting advertiser
        $advert = Advert::first(array("user_id = ?" => $user->id));
        if ($advert) {
            if ($advert->live == 0) {
                return "Account Suspended";
            }
            $this->setUser($user);
            $session->set("advert", $advert);
            $this->redirect("/advertiser/index.html");
        }
    }

    protected function _resetPassword() {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")), array("id", "email", "name"));
        if ($exist) {
            $meta = new Meta(array(
                "user_id" => $exist->id,
                "property" => "resetpass",
                "value" => uniqid()
            ));
            $meta->save();
            $this->notify(array(
                "template" => "forgotPassword",
                "subject" => "New Password Requested",
                "user" => $exist,
                "meta" => $meta
            ));

            return "Password Reset Email Sent Check Your Email. Check in Spam too.";
        } else {
            return "User doesnot exist.";
        }
    }

    protected function _publisherRegister() {
        $pass = $this->randomPassword();
        $user = new User(array(
            "username" => RequestMethods::post("name"),
            "name" => RequestMethods::post("name"),
            "email" => RequestMethods::post("email"),
            "password" => sha1($pass),
            "phone" => RequestMethods::post("phone"),
            "country" => $this->country(),
            "live" => 1
        ));
        if (RequestMethods::post("action") == "fblogin") {
            $user->phone = "0";
        }
        if ($user->validate()) {
            $user->save();
        } else {
            return $user->getErrors();
        }
        
        $platform = new Platform(array(
            "user_id" => $user->id,
            "type" => "FACEBOOK_PAGE",
            "url" =>  RequestMethods::post("url")
        ));
        if ($platform->validate()) {
            $platform->save();
        } else {
            $user->delete();
            return $platform->getErrors();
        }

        $publish = new Publish(array(
            "user_id" => $user->id,
            "bouncerate" => 0,
            "account" => "basic",
            "live" => 1
        ));
        if ($publish->validate()) {
            $publish->save();

            $this->notify(array(
                "template" => "publisherRegister",
                "subject" => "Welcome to Clicks99",
                "user" => $user,
                "pass" => $pass
            ));
        } else {
            $publish->delete();
            $user->delete();
            return $publish->getErrors();
        }
    }

    protected function _advertiserRegister() {
        $pass = $this->randomPassword();
        $user = new User(array(
            "username" => RequestMethods::post("name"),
            "name" => RequestMethods::post("name"),
            "email" => RequestMethods::post("email"),
            "password" => sha1($pass),
            "phone" => RequestMethods::post("phone"),
            "country" => $this->country(),
            "live" => 0
        ));
        if ($user->validate()) {
            $user->save();
        } else {
            return $user->getErrors();
        }
        
        $platform = new Platform(array(
            "user_id" => $user->id,
            "type" => "WEBSITE",
            "url" =>  RequestMethods::post("url")
        ));
        if ($platform->validate()) {
            $platform->save();
        } else {
            $user->delete();
            return $platform->getErrors();
        }

        $advert = new Advert(array(
            "user_id" => $user->id,
            "account" => "basic",
            "cpc" => "",
            "gatoken" => "",
            "live" => 0
        ));
        if ($advert->validate()) {
            $advert->save();

            $this->notify(array(
                "template" => "advertiserRegister",
                "subject" => "Welcome to Clicks99",
                "user" => $user,
                "pass" => $pass
            ));
        } else {
            $user->delete();
            $platform->delete();
            return $advert->getErrors();
        }
    }

    protected function randomPassword() { 
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    public function account() {
        $this->noview();
        $session = Registry::get("session");

        $team = $session->get("team");
        if ($team) {
            $this->redirect("/manage/index.html");
        }

        $publish = $session->get("publish");
        if ($publish) {
            $this->redirect("/publisher/index.html");
        }

        $advert = $session->get("advert");
        if ($advert) {
            $this->redirect("/advertiser/index.html");
        }

        $this->redirect("/index.html");
    }

    protected function reCaptcha() {
        $g_recaptcha_response = RequestMethods::post("g-recaptcha-response");
        $curl = new Curl();
        $curl->post('https://www.google.com/recaptcha/api/siteverify', array(
            'secret' => '6LfRZRQTAAAAABxnjW_9e6x_BgzVc_b2ghnxmE8D',
            'response' => $g_recaptcha_response
        ));
        return $curl->response->success;
    }

    /**
     * @before _secure, _admin
     */
    public function loginas($user_id) {
        $this->setUser(false);
        $user = User::first(array("id = ?" => $user_id));
        $this->authorize($user);
    }

    protected function country() {
        require_once '/var/www/ctracker/includes/vendor/autoload.php';
        $reader = new GeoIp2\Database\Reader('/var/www/ctracker/includes/GeoLite2-Country.mmdb');
        $record = $reader->country(Shared\Markup::get_client_ip());
        return $record->country->isoCode;
    }

    public function contact() {
        $this->seo(array("title" => "Contact Us", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if ($this->user) {
            $this->redirect("/support/tickets.html");
        }

        if (RequestMethods::post("message")) {
            $email = RequestMethods::post("email");
            $user = User::first(array("email = ?" => $email), array("id"));
            if (!isset($user)) {
                $name = RequestMethods::post("name");
                $user = new User(array(
                    "username" => $name,
                    "name" => $name,
                    "email" => $email,
                    "password" => sha1($this->randomPassword()),
                    "phone" => "",
                    "admin" => 0,
                    "live" => 0
                ));
                $user->save();
            }
            $ticket = new Ticket(array(
                "user_id" => $user->id,
                "subject" => RequestMethods::post("subject"),
                "type" => "contact",
                "live" => true
            ));
            $ticket->save();
            $conversation = new Conversation(array(
                "user_id" => $user->id,
                "ticket_id" => $ticket->id,
                "message" => RequestMethods::post("message"),
                "file" => "",
            ));
            $conversation->save();
            $view->set("message", "Submitted Successfully");
        }
    }
}
