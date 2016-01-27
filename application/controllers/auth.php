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
     * @before _session, _blank
     */
    public function login() {
        $this->seo(array("title" => "Login", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::get("action") == "reset") {
            $this->_resetPassword();
        }
        
        if (RequestMethods::post("action") == "login") {
            $this->_login();
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
                $login = Meta::first(array("property = ?" => "login"));
                if($login->value == "yes") {
                    if ($user->live) {
                        $this->session($user);
                    } else {
                        $view->set("message", "User account not verified");
                    }
                } else {
                    if ($user->admin) {
                        $this->session($user);
                    }
                    $view->set("message", "We are Updating our System, try later");
                }
            } else{
                $view->set("message", 'Wrong Password, Try again or <a href="/auth/login?action=reset&email='.$email.'">Reset Password</a>');
            }
            
        } else {
            $view->set("message", 'User doesnot exist. Please signup <a href="/auth/register">here</a>');
        }
    }

    protected function _resetPassword() {
        $exist = User::first(array("email = ?" => RequestMethods::get("email")), array("id", "email", "name"));
        if ($exist) {
            $this->notify(array(
                "template" => "forgotPassword",
                "subject" => "New Password Requested",
                "user" => $exist
            ));

            $view->set("message", "Password Reset Email Sent Check Your Email. Check in Spam too.");
        }
    }
    
    /**
     * @before _session, _blank
     */
    public function register() {
        $this->seo(array(
            "title" => "Register",
            "view" => $this->getLayoutView()
        ));
        $view = $this->getActionView();
        
        if (RequestMethods::post("action") == "register" && $this->reCaptcha()) {
            $this->_register();
        }
    }

    protected function _register() {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if (!$exist) {
            $user = new User(array(
                "username" => RequestMethods::post("username"),
                "name" => RequestMethods::post("name"),
                "email" => RequestMethods::post("email"),
                "password" => sha1(RequestMethods::post("password")),
                "phone" => RequestMethods::post("phone"),
                "admin" => 0,
                "live" => 0
            ));
            $user->save();
            
            $platform = new Platform(array(
                "user_id" => $user->id,
                "name" => "FACEBOOK_PAGE",
                "link" =>  RequestMethods::post("link"),
                "image" => $this->_upload("fbadmin", "images")
            ));
            $platform->save();

            $publish = new Publish(array(
                "user_id" => $user->id,
                "domain" => "",
                "fblink" => RequestMethods::post("fblink")
            ));
            $publish->save();

            $this->notify(array(
                "template" => "publisherRegister",
                "subject" => "Welcome to ChocoGhar.com",
                "user" => $user
            ));
            $view->set("message", "Your account has been created and will be activate within 3 hours after verification.");
        } else {
            $view->set("message", 'Username exists, login from <a href="/admin/login">here</a>');
        }
    }

    /**
     * @before _session, _blank
     */
    public function forgotpassword() {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "change") {
            $token = RequestMethods::post("token");
            $id = base64_decode($token);
            $user = User::first(array("id = ?" => $id));
            if(RequestMethods::post("password") == RequestMethods::post("cpassword")) {
                $user->password = sha1(RequestMethods::post("password"));
                $user->save();
                $this->session($user);
            } else{
                $view->set("message", 'Password Does not match');
            }
        }

        if (RequestMethods::get("action") == "reset") {
            $token = RequestMethods::get("token");
            $id = base64_decode($token);
            $exist = User::first(array("id = ?" => $id), array("id"));
            if($exist) {
                $view->set("token", $token);
            } else{
                $view->set("message", 'Something Went Wrong please contact admin');
            }
        }
    }

    protected function session($user) {
        $this->setUser($user);
        $session = Registry::get("session");
        //setting domains
        $domains = Meta::all(array("property = ?" => "domain", "live = ?" => true));
        $session->set("domains", $domains);

        //setting publisher
        $publish = Publish::first(array("user_id = ?" => $user->id));
        if ($publish) {
            $session->set("publish", $publish);
            self::redirect("/publisher");
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
    
    /**
     * @protected
     */
    public function _blank() {
        $this->defaultLayout = "layouts/blank";
        $this->setLayout();
    }
}
