<?php
/**
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
    public function register() {
        $this->seo(array("title" => "Register as Publisher", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "register") {
            $exist = User::first(array("email = ?" => RequestMethods::post("email")));
            if ($exist) {
                $view->set("message", 'User exists, <a href="/auth/login.html">login</a>');
            } else {
                $errors = $this->_register();
                $view->set("errors", $errors);
                if (empty($errors)) {
                    $view->set("message", "Your account has been created, Check your email for password");
                }
            }
        }
    }
    
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
        //setting staff
        $staff = Staff::first(array("user_id = ?" => $user->id));
        if ($staff) {
            if ($staff->live == 0) {
                return "Account Suspended";
            }
            $this->setUser($user);
            $session->set("staff", $staff);
            $this->redirect("/admin/index.html");
        }

        //setting customer
        $customer = Customer::first(array("user_id = ?" => $user->id));
        if ($customer) {
            $this->setUser($user);
            $session->set("customer", $customer);
            switch ($customer->type) {
                case 'monetize':
                    $this->redirect("/publisher/index.html");
                    break;
                
                default:
                    $this->redirect("/advertiser/index.html");
                    break;
            }
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

    protected function _register() {
        $pass = $this->randomPassword();
        $user = new User(array(
            "username" => RequestMethods::post("name"),
            "name" => RequestMethods::post("name"),
            "email" => RequestMethods::post("email"),
            "password" => sha1($pass),
            "phone" => RequestMethods::post("phone"),
            "currency" => "INR",
            "live" => 0
        ));
        if ($user->validate()) {
            $user->save();
        } else {
            return $user->getErrors();
        }
        
        $customer = new Customer(array(
            "user_id" => $user->id,
            "country" => $this->country(),
            "balance" => 0,
            "staff_id" => 0,
            "type" => RequestMethods::post("type"),
            "live" => 0
        ));
        if ($customer->validate()) {
            $customer->save();

            $platform = new Platform(array(
                "user_id" => $user->id,
                "type" => "",
                "category" => "",
                "url" => RequestMethods::post("url"),
                "live" => 0
            ));
            $platform->save();

            $this->notify(array(
                "template" => "register",
                "subject" => "Welcome to vNative",
                "user" => $user,
                "pass" => $pass
            ));
        } else {
            $customer->delete();
            $user->delete();
            return $customer->getErrors();
        }
    }

    public function randomtext() {
        $this->JSONview();
        $view = $this->getActionView();
        $pass = $this->randomPassword();

        $view->set("random", $pass);
        $view->set("hash", sha1($pass));
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

        $staff = $session->get("staff");
        if ($staff) {
            $this->redirect("/admin/index.html");
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

    /**
     * @protected
     */
    public function _layout() {
        $this->defaultLayout = "layouts/account";
        $this->setLayout();
    }
}
