<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use \Curl\Curl;

class Facebook extends Auth {

    public function fblogin() {
        $this->JSONview();
        $view = $this->getActionView();
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if($exist) {
            if ($exist->live && RequestMethods::post("action") == "fblogin") {
                $view->set("success", $this->authorize($exist));
            } else {
                $view->set("success", false);
            }
        } else {
            $view->set("error", $this->_publisherRegister());
            $user = User::first(array("email = ?" => RequestMethods::post("email")));
            $view->set("success", $this->authorize($user));
        }
    }

    public function fbauthorize() {
        $this->JSONview();
        $view = $this->getActionView();
        if ($this->user && RequestMethods::post("action") == "fbauthorize") {
            $email = RequestMethods::post("email"); $fbid = RequestMethods::post("fbid");
            $socialfb = SocialFB::first([
                "user_id = ?" => $this->user->id,
                "email = ?" => $email,
                "fbid = ?" => $fbid
            ]);

            if (!$socialfb) {
                $conf = Registry::get("configuration");
                $app = $conf->parse("configuration/fb")->app->clicks99;

                $fb = new Curl();
                $fb->get('https://graph.facebook.com/oauth/access_token', [
                    'client_id' => $app->id,
                    'client_secret' => $app->secret,
                    'grant_type' => 'fb_exchange_token',
                    'fb_exchange_token' => RequestMethods::post("access_token")
                ]);
                $response = str_replace('access_token=', '', $fb->response);
                $fb->close();

                $socialfb = new SocialFB([
                    "user_id" => $this->user->id,
                    "email" => $email,
                    "fbid" => $fbid, "live" => 1,
                    "fbtoken" => $response
                ]);
                $socialfb->save();

                $this->_storePages($response);
            }
            $view->set("success", true);
        }
    }

    protected function _storePages($token) {
        if (!$token) {
            throw new \Exception("Invalid Token supplied for request");
        }
        $fb = new Curl();
        $fb->get('https://graph.facebook.com/me/accounts', [
            'access_token' => $token,
            'fields' => 'name,id,can_post,category,access_token,likes,website'
        ]);
        $response = $fb->response;
        $fb->close();

        $pages = is_array($response->data) ? $response->data : [];
        foreach ($pages as $p) {
            if (!$p->can_post) continue;

            $this->_savePage($p);
        }
    }

    /**
     * Saves the FBPage details in DB and grants access to the user
     * to that page
     */
    protected function _savePage($obj) {
        $fbpage = FBPage::first(["fbid = ?" => $obj->id]);
        if (!$fbpage) {
            $fbpage = new FBPage([
                'fbid' => $obj->id,
                'user_id' => $this->user->id,
                'live' => 1,
                'token' => $obj->access_token
            ]);
        }
        $fbpage->name = $obj->name;
        $fbpage->category = $obj->category;
        $fbpage->likes = $obj->likes;
        $fbpage->website = $obj->website;
        $fbpage->save();    // save page

        // grant access
        $access = Access::first(["property = ?" => "fbpage", "property_id = ?" => $fbpage->id, "user_id = ?" => $this->user->id]);
        if (!$access) {
            $access = new Access([
                'property' => "fbpage",
                'property_id' => $fbpage->id,
                'user_id' => $this->user->id,
                'live' => 1
            ]);
            $access->save();
        }
    }

    /**
     * @before _secure
     */
    public function addpage() {
        $this->JSONview();
        $view = $this->getActionView();
        if (RequestMethods::post("can_post") == "true") {
            $obj = ArrayMethods::toObject([
                'id' => RequestMethods::post("id"),
                'name' => RequestMethods::post("name"),
                'category' => RequestMethods::post("category"),
                'likes' => RequestMethods::post("likes"),
                'website' => RequestMethods::post("website"),
                'token' => ""
            ]);
            $this->_savePage($obj);
            $view->set("success", true);
        } else {
            $view->set("success", false);
        }
    }

    /**
     * @before _secure
     */
    public function postStats() {
        $this->JSONview();
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "showStats") {
            $link_id = RequestMethods::post("link_id");
            $link = Link::first(["id = ?" => $link_id, "user_id = ?" => $this->user->id]);

            if (!$link) {
                $view->set("success", false);
                return;
            }

            $fbpost = FBPost::first(["link_id = ?" => $link->id]);
            if (!$fbpost) {
                $view->set("success", false);
                return;
            }

            $fbpage = FBPage::first(["fbid = ?" => $fbpost->fbpage_id], ["token"]);
            $fb = new Curl();
            $fb->get('https://graph.facebook.com/' . $fbpost->fbpost_id . '/insights/post_consumptions_by_type/', [
                'access_token' => $fbpage->token,
                'fields' => 'id,name,period,title,values'
            ]);

            $response = $fb->response;
            $fb->close();
            
            $data = (is_array($response->data)) ? array_shift($response->data) : [];
            if (property_exists($data, 'values')) {
                $arr = array_shift($data->values); $c = 'link clicks';
                $clicks = $arr->value->$c;
            } else {
                $clicks = 0;
            }
            $view->set("clicks", $clicks)
                ->set("success", true);
        }
    }

    /**
     * @before _secure
     */
    public function pagePost() {
        $this->JSONview();
        $view = $this->getActionView();
        if (RequestMethods::post("action") == "addPost") {
            $postid = RequestMethods::post("postid"); $pageid = RequestMethods::post("pageid");
            $link_id = RequestMethods::post("link_id"); $type = RequestMethods::post("type", "click");
            $fbPost = FBPost::first(["user_id = ?" => $this->user->id, "fbpage_id = ?" => $pageid, "link_id = ?" => $link_id]);

            if (!$fbPost) {
                $fbPost = new FBPost([
                    "user_id" => $this->user->id,
                    "fbpage_id" => $pageid,
                    "fbpost_id" => $postid,
                    "link_id" => $link_id,
                    "type" => "click",
                    "count" => 0,
                    "live" => 1
                ]);   
            }

            if ($fbPost->validate()) {
                $fbPost->save();
                $view->set("success", true);
            } else {
                $view->set("success", false);
            }
        } else {
            $view->set("success", false);
        }
    }
}
