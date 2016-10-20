<?php

/**
 * Subclass the Controller class within our application.
 *
 * @author Faizan Ayubi
 */

namespace Shared {

    use Framework\Events as Events;
    use Framework\Router as Router;
    use Framework\Registry as Registry;
    use Framework\RequestMethods as RequestMethods;
    use Aws\S3\S3Client;

    class Controller extends \Framework\Controller {

        /**
         * @readwrite
         */
        protected $_user;

        /**
         * @readwrite
         */
        protected $_domain = null;

        /**
         * @readwrite
         */
        protected $_org = null;

        public function setOrg($org = null) {
            $session = Registry::get("session");
            if ($org) {
                $session->set("org", $org);
            } else {
                $session->erase("org");
            }
            $this->_org = $org;
            return $this;
        }

        public function getOrg() {
            $session = Registry::get("session");
            $this->_org = $session->get("org");
            return $this->_org;
        }
        
        public function seo($params = array()) {
            $seo = Registry::get("seo");
            foreach ($params as $key => $value) {
                $property = "set" . ucfirst($key);
                $seo->$property($value);
            }
            $this->layoutView->set("seo", $seo);
        }

        /**
         * @protected
         */
        public function _admin() {
            $user = $this->getUser();
            if (!$user || $user->type != "admin") {
                $this->logout();
            }
        }

        public function currency($n) {
            $n = (float) $n;
            $user = $this->getUser();
            switch (strtolower($user->currency)) {
                case 'inr':
                    $v = $n/66;
                    break;

                case 'pkr':
                    $v = $n/104;
                    break;

                case 'aud':
                    $v = $n/1.3;
                    break;

                case 'eur':
                    $v = $n/0.9;
                    break;

                case 'gbp':
                    $v = $n/0.8;
                    break;
                
                default:
                    $v = $n;
                    break;
            }
            return $v;
        }

        /**
         * @protected
         */
        public function _secure() {
            $user = $this->getUser();
            if (!$user) {
                Registry::get("session")->set('$beforeLogin', RequestMethods::server('REQUEST_URI', '/'));
                $this->redirect("/login.html");
            }
        }

        /**
         * @protected
         */
        public function _session() {
            $user = $this->getUser();
            if ($user) {
                switch ($user->type) {
                    case 'admin':
                        $this->redirect('/admin.html');
                        break;
                    
                    case 'publisher':
                        $this->redirect('/publisher.html');
                        break;

                    case 'advertiser':
                        $this->redirect('/advertiser.html');
                        break;
                }
            }
        }

        public function redirect($url) {
            $this->noview();
            header("Location: {$url}");
            exit();
        }

        public function _404($msg = "Invalid Request") {
            $this->noview();
            throw new \Framework\Router\Exception\Controller($msg);
        }

        public function setUser($user) {
            $session = Registry::get("session");
            if ($user) {
                $session->set("user", $user->id);
            } else {
                $session->erase("user");
            }
            $this->_user = $user;
            return $this;
        }

        protected function log($message = "") {
            $logfile = APP_PATH . "/logs/" . date("Y-m-d") . ".txt";
            $new = file_exists($logfile) ? false : true;
            if ($handle = fopen($logfile, 'a')) {
                $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
                $content = "[{$timestamp}]{$message}\n";
                fwrite($handle, $content);
                fclose($handle);
                if ($new) {
                    chmod($logfile, 0777);
                }
            }
        }
        
        public function logout() {
            $this->setUser(false);
            session_destroy();
            self::redirect("/index.html");
        }
        
        public function noview() {
            $this->willRenderLayoutView = false;
            $this->willRenderActionView = false;
        }

        public function JSONview() {
            $this->willRenderLayoutView = false;
            $this->defaultExtension = "json";
            $this->defaultContentType = "application/json";
        }
        
        /**
         * The method checks whether a file has been uploaded. If it has, the method attempts to move the file to a permanent location.
         * @param string $name
         * @param string $type files or images
         */
        protected function _upload($name, $type = "images", $opts = []) {
            if (isset($_FILES[$name])) {
                $file = $_FILES[$name];
                $path = APP_PATH . "/public/assets/uploads/{$type}/";
                $extension = pathinfo($file["name"], PATHINFO_EXTENSION);

                if (isset($opts['extension'])) {
                    $ex = $opts['extension'];

                    if (!preg_match("/^".$ex."$/", $extension)) {
                        return false;
                    }
                }

                if (isset($opts['name'])) {
                    $filename = $opts['name'];
                } else {
                    $filename = uniqid() . ".{$extension}";
                }
                if (move_uploaded_file($file["tmp_name"], $path . $filename)) {
                    return $filename;
                }
            }
            return FALSE;
        }

        public function __construct($options = array()) {
            parent::__construct($options);

            Services\Db::connect();

            // schedule: load user from session           
            Events::add("framework.router.beforehooks.before", function($name, $parameters) {
                $session = Registry::get("session");
                $controller = Registry::get("controller");
                $user = $session->get("user");
                if ($user) {
                    $controller->user = \User::first(array("id = ?" => $user));
                }
            });

            // schedule: save user to session
            Events::add("framework.router.afterhooks.after", function($name, $parameters) {
                $session = Registry::get("session");
                $controller = Registry::get("controller");
                if ($controller->user) {
                    $session->set("user", $controller->user->id);
                }

                // Set Flash Message to the Action View
                $flashMessage =  $session->get('$flashMessage', null);
                if ($flashMessage) {
                    $session->erase('$flashMessage');
                    $controller->actionView->set('message', $flashMessage);
                }
            });
        }

        public function __destruct() {
            $view = $this->layoutView;
            if ($view && !$view->get('seo')) {
                $view->set('seo', \Framework\Registry::get("seo"));
            }
            parent::__destruct();
        }

        /**
         * Checks whether the user is set and then assign it to both the layout and action views.
         */
        public function render() {
            /* if the user and view(s) are defined, 
             * assign the user session to the view(s)
             */
            if ($this->user) {
                if ($this->actionView) {
                    $key = "user";
                    if ($this->actionView->get($key, false)) {
                        $key = "__user";
                    }
                    $this->actionView->set($key, $this->user);
                }
                if ($this->layoutView) {
                    $key = "user";
                    if ($this->layoutView->get($key, false)) {
                        $key = "__user";
                    }
                    $this->layoutView->set($key, $this->user);
                }
            }
            if ($this->_org) {
                if ($this->actionView) {
                    $key = "org";
                    if ($this->actionView->get($key, false)) {
                        $key = "__org";
                    }
                    $this->actionView->set($key, $this->org);
                }
                if ($this->layoutView) {
                    $key = "org";
                    if ($this->layoutView->get($key, false)) {
                        $key = "__org";
                    }
                    $this->layoutView->set($key, $this->org);
                }
            }
            parent::render();
        }

    }

}
