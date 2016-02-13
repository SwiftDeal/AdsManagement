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
        
        if (RequestMethods::post("action") == "login") {
            $message =  $this->_login();
            $view->set("message", $message);
        }
    }

    /**
     * @before _session
     */
    public function forgotpassword() {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::get("action") == "reset" && $this->reCaptcha()) {
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
            slef::redirect("/index.html");
        }

        if (RequestMethods::post("action") == "change") {
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
        $email = RequestMethods::post("email");
        $exist = User::first(array("email = ?" => $email), array("id", "email"));
        if($exist) {
            $user = User::first(array(
                "email = ?" => RequestMethods::post("email"),
                "password = ?" => sha1(RequestMethods::post("password"))
            ));
            if($user) {
                if ($user->live) {
                    return $this->session($user);
                } else {
                    return "User account not verified";
                }
            } else{
                return 'Wrong Password, Try again or <a href="/auth/forgotpassword.html">Reset Password</a>';
            }
            
        } else {
            return 'User doesnot exist. Please signup <a href="/auth/register">here</a>';
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
            $this->notify(array(
                "template" => "forgotPassword",
                "subject" => "New Password Requested",
                "user" => $exist,
                "meta" => $meta
            ));

            $view->set("message", "Password Reset Email Sent Check Your Email. Check in Spam too.");
        }
    }

    protected function _publisherRegister() {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if (!$exist) {
            $pass = $this->randomPassword();
            $user = new User(array(
                "username" => RequestMethods::post("name"),
                "name" => RequestMethods::post("name"),
                "email" => RequestMethods::post("email"),
                "password" => sha1($pass),
                "phone" => RequestMethods::post("phone"),
                "admin" => 0,
                "currency" => "INR",
                "live" => 0
            ));
            $user->save();
            
            $platform = new Platform(array(
                "user_id" => $user->id,
                "type" => "FACEBOOK_PAGE",
                "url" =>  RequestMethods::post("url")
            ));
            $platform->save();

            $publish = new Publish(array(
                "user_id" => $user->id,
                "country" => $this->country(),
                "live" => 1
            ));
            $publish->save();

            $this->notify(array(
                "template" => "publisherRegister",
                "subject" => "Welcome to Clicks99",
                "user" => $user,
                "pass" => $pass
            ));
            return "Your account has been created, we will notify you once approved.";
        } else {
            return 'User exists, <a href="/auth/login.html">login</a>';
        }
    }

    protected function _advertiserRegister() {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if (!$exist) {
            $pass = $this->randomPassword();
            $user = new User(array(
                "username" => RequestMethods::post("username"),
                "name" => RequestMethods::post("name"),
                "email" => RequestMethods::post("email"),
                "password" => sha1($pass),
                "phone" => RequestMethods::post("phone"),
                "admin" => 0,
                "currency" => "INR",
                "live" => 0
            ));
            $user->save();
            
            $platform = new Platform(array(
                "user_id" => $user->id,
                "type" => "WEBSITE",
                "url" =>  RequestMethods::post("url")
            ));
            $platform->save();

            $advert = new Advert(array(
                "user_id" => $user->id,
                "country" => $this->country(),
                "live" => 1
            ));
            $publish->save();

            return "Your account has been created, we will notify you once approved.";
        } else {
            return 'User exists, <a href="/auth/login.html">login</a>';
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

    protected function session($user) {
        $session = Registry::get("session");
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
            self::redirect("/publisher");
        }

        //setting publisher
        $advert = Advert::first(array("user_id = ?" => $user->id));
        if ($advert) {
            if ($advert->live == 0) {
                return "Account Suspended";
            }
            $this->setUser($user);
            $session->set("advert", $advert);
            self::redirect("/advertiser");
        }
    }

    protected function reCaptcha() {
        $g_recaptcha_response = RequestMethods::post("g-recaptcha-response", '03AHJ_Vut4zQ1SeLzhjSRqza49bNphfWsmpzejAkFADRA5hUN9hcBZdDq6fr8iA8aUhmJPlWxrG_7ImqQ3mheofTBVoT8MhUJRWNLTGpUofNVeVPREHmVqBPOQC80S70Gpt5UzT4E390NOhrOxc93gR5-h998bllqaWF180_yyMX2JqsfsgBx8R1gED5sJ8Zu8M7aRVRrAMBvfx8BBmUOG5QPnvPhcdIaq9pkCYiNC2Smr_JDLoL9jeB2uFGFsNaZaNuAIqffi9d_aF0HypLlo3TOCyhrPSeIz8lLmMpamOGjJqq_62SufW4vJ4ZYBSG4dBW0J_g7saqWljmULRJqW9veba-3AIErK7cDoDmgZCP9HtKDgx2ZOQXkvS_8Rnqwj-iAXFmHsLmOJ8CBMQ4j0dYwEHelkwlL6q5hUOqswrWrCab8eZ8xXEhme-qsjoHeTSlbwGSXHW5i-gPV_-qKOkWwxGDKkFzk6QkaJ6vRXRoGygaTcYQXO4ClDaUZOgKPJEhK0LbS-Hs4excNlCk-Ff23wiwGcyuqOOZ1oRH-L9X1eNwqHd-MFFZoPG99s45gURcHA4UMEPkAy60WT8BHGD1Z3_HbsVTX7Ana9d0vyuhr0ou7E0kWfwdHCrEQ5jOpp2J4z4Kn6aeMnpExO028-4VhAL-pGy_gcErEohBaQO7BZPtR4jB7iC4iytQrl6u5KDaSkWHO9KunFgO3UCHzoA3C08z6PjkU4Eq5fYSy5bwTMH_R6WHsH3RON0VQwJCuJu8XlwnDwKqC5siYVJ5EVE50r0xNEAGQgPUjwZIHFzoZO0g7NejN_1p3hgzpoAYFaW6mSzXxK08aaLD0nbxRWxWO9fbIIPQfRVLnkSMHikTZyqwPBJyVQJlEe65CFV1KdDzYUsAOZH3wUKFRC1L4EgkRLlvFnq1wlRA');
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
        $this->session($user);
    }

    protected function country() {
        require '/var/www/powerfeeds/includes/vendor/autoload.php';
        $reader = new GeoIp2\Database\Reader('/var/www/powerfeeds/includes/GeoLite2-Country.mmdb');
        $record = $reader->country(Shared\Markup::get_client_ip());
        return $record->country->isoCode;
    }
}
