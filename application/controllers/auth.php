<?php
/**
 * @author Faizan Ayubi
 */
use Shared\Controller as Controller;
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use \Curl\Curl;

class Auth extends Controller {

    public function __construct($options = []) {
        parent::__construct($options);
        $host = RequestMethods::server('SERVER_NAME');
        if (strpos($host, "vmtraffic.com")) {
            $domain = explode(".", $host);
            $domain = array_shift($domain);
            $q = ["domain = ?" => $domain];
        } else {
            $domain = $host;
            $q = ["url = ?" => $domain];
        }

        $this->domain = $domain;

        if (!$this->org) {
            $org = \Organization::first($q);
            if (!$org) {
                $this->redirect('/500');
            } else {
                $this->org = $org;
            }
        }
    }
    
    /**
     * @before _session
     */
    public function login() {
        $this->seo(array("title" => "Login", "view" => $this->getLayoutView()));
        $view = $this->getActionView(); $session = Registry::get("session");

        $csrf_token = $session->get('Auth\Login:$token');
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "login" && $csrf_token && $token === $csrf_token) {
            $this->_login($this->org, $view);
        }
        $csrf_token = Framework\StringMethods::uniqRandString(44);
        $session->set('Auth\Login:$token', $csrf_token);
        $view->set('__token', $csrf_token);
        $view->set('organization', $this->org);
    }

    protected function _login($org, $view) {
        $session = Registry::get("session");
        $email = RequestMethods::post("email"); $pass = RequestMethods::post("password");
        $user = \User::first(["organization_id = ?" => $org->_id, "email = ?" => $email]);

        if (!$user) {
            return $view->set('message', 'Invalid credentials');
        } else if (sha1($pass) != $user->password) {
            return $view->set('message', 'Invalid credentials');
        } else if (!$user->live) {
            return $view->set('message', 'User account deactivated!!');
        }
        $session->erase('Auth\Login:$token');   // erase login token
        $this->_loginRedirect($user, $org);
    }

    protected function _loginRedirect($user, $org) {
        $session = Registry::get("session");
        $this->setUser($user); $this->setOrg($org);
        $beforeLogin = $session->get('$beforeLogin');
        if ($beforeLogin) {
            $session->erase('$beforeLogin');
            $this->redirect($beforeLogin);
        }

        switch ($user->type) {
            case 'admin':
                $this->redirect('/admin/index.html');
                break;
            
            case 'publisher':
                $this->redirect('/publisher/index.html');
                break;

            case 'advertiser':
                $this->redirect('/advertiser/index.html');
                break;
        }
    }

    /**
     * @before _session
     */
    public function forgotpassword() {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $view->set('organization', $this->org);

        // @todo install reCaptcha
        if (RequestMethods::post("action") == "forgot") {
            $message = $this->_forgotPassword($this->org);
            $view->set("message", $message);
        }
    }

    /**
     * @before _session
     */
    public function resetpassword($token) {
        $this->seo(array("title" => "Forgot Password", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $view->set('organization', $this->org);

        $meta = Meta::first(array("value = ?" => $token, "prop = ?" => "resetpass"));
        if (!isset($meta)) {
            $this->redirect("/index.html");
        }

        // @todo install reCaptcha
        if (RequestMethods::post("action") == "change") {
            $pass = RequestMethods::post("password");
            $user = User::first(array("id = ?" => $meta->propid));
            if ($pass == RequestMethods::post("npassword")) {
                $user->password = sha1($pass);
                $user->save();
                $meta->delete();
                $view->set("message", 'Password changed successfully now <a href="/login.html">Login</a>');
            } else{
                $view->set("message", 'Password Does not match');
            }
        }
    }

    protected function _forgotPassword($org) {
        $exist = User::first(array("email = ?" => RequestMethods::post("email")), array("id", "email", "name"));
        if ($exist) {
            $meta = new Meta(array(
                "propid" => $exist->id,
                "prop" => "resetpass",
                "value" => uniqid()
            ));
            $meta->save();
            Shared\Mail::send(array(
                "template" => "forgotpass",
                "subject" => "New Password Requested",
                "user" => $exist,
                "meta" => $meta,
                'app' => $org->domain,
                'subdomain' => $org->domain,
                'team' => $org->name
            ));
        }
        return "Password Reset Email Sent Check Your Email. Check in Spam too.";
    }

    protected function _publisherRegister($org, $view) {
        $email = RequestMethods::post("email");
        $platformUrl = RequestMethods::post("platform", '');
        $exist = \User::first(['email = ?' => $email, 'organization_id = ?' => $org->_id]);
        if ($exist) {
            return $view->set('message', 'Email already exists!!');
        }

        try {
            $platform = new \Platform([
                'url' => $platformUrl
            ]);
        } catch (\Exception $e) {
            return $view->set('message', $e->getMessage());
        }
        $user = new User(array(
            "organization_id" => $org->id,
            "name" => RequestMethods::post("name"),
            "email" => $email,
            "password" => sha1(RequestMethods::post("password")),
            "phone" => RequestMethods::post("phone"),
            "country" => RequestMethods::server("HTTP_CF_IPCOUNTRY", "IN"),
            "currency" => "INR",
            "type" => "publisher",
            "live" => false
        ));
        if ($user->validate()) {
            $user->save();
            
            $platform->user_id = $user->_id;
            $platform->meta = null;
            $platform->save();
            return $view->set('message', "Registered Successfully");
        } else {
            return $view->set('errors', $user->getErrors());
        }
    }

    protected function _advertiserRegister($org, $view) {
        $pass = Shared\Utils::randomPass();
        $email = RequestMethods::post("email");
        $platformUrl = RequestMethods::post("url", '');
        $exist = \User::first(['email = ?' => $email, 'organization_id = ?' => $org->_id]);
        if ($exist) {
            return $view->set('message', "Email already exists!!");
        }

        try {
            $platform = new \Platform([
                'url' => $platformUrl
            ]);
        } catch (\Exception $e) {
            return $view->set('message', $e->getMessage());
        }

        $user = new User(array(
            "organization_id" => $org->id,
            "name" => RequestMethods::post("name"),
            "email" => $email,
            "password" => sha1(RequestMethods::post("password")),
            "phone" => RequestMethods::post("phone"),
            "country" => RequestMethods::server("HTTP_CF_IPCOUNTRY", "IN"),
            "currency" => "INR",
            "type" => "advertiser",
            "live" => false
        ));
        if ($user->validate()) {
            $user->save();
        } else {
            return $view->set('errors', $user->getErrors());
        }
        
        $platform->user_id = $user->_id;
        $platform->meta = null;
        $platform->save();
        return $view->set('message', "Registered Successfully");
    }

    /**
     * Login as a User
     * @before _admin
     */
    public function loginas($user_id) {
        $session = Registry::get("session");
        $session->set("admin_user_id", $this->user->id);
        $this->setUser(false);
        $user = User::first(array("_id = ?" => $user_id));
        $org = Organization::first(["_id = ?" => $user->organization_id]);
        $this->_loginRedirect($user, $org);
    }

    public function logout() {
        $session = Registry::get("session");
        $this->setUser(false);

        $admin = $session->get("admin_user_id");
        if (!$admin) {
            session_destroy();
            $this->redirect("/");
        } else {
            $user = User::first(["id = ?" => $admin]);
            $org = Organization::first(["_id = ?" => $user->organization_id]);
            $session->erase("admin_user_id");
            $this->_loginRedirect($user, $org);
        }
    }

    /**
     * @protected
     */
    public function _publisher() {
        parent::_secure();
        if ($this->user->type !== 'publisher' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/publisher");
    }

    protected function perf($clicks, $p=null) {
        $perf = new Performance();
        
        $classify = \Click::classify($clicks, 'adid');
        foreach ($classify as $key => $value) {
            if (!array_key_exists($key, $adsInfo)) {
                $comm = \Commission::first(['ad_id = ?' => $key], ['model', 'rate']);
                $adsInfo[$key] = $comm;
            } else {
                $comm = $adsInfo[$key];
            }

            // Check for click fraud
            $uniqClicks = Click::checkFraud($value);
            $adClicks = count($uniqClicks);

            if (isset($p->meta['campaign']) && !is_null($p->meta['campaign']['rate'])) {
                $rate = $p->meta['campaign']['rate'];
            } else {
                $rate = $comm->rate;
            }
            $revenue = ((float) $rate) * $adClicks;

            $perf->clicks += $adClicks;
            $perf->revenue += round($revenue, 6);
        }

        if ($perf->clicks === 0) {
            continue;
        } else {
            $avgCpc = $perf->revenue / $perf->clicks;
        }
        $perf->cpc = round($avgCpc, 6);

        return $perf;
    }
}
