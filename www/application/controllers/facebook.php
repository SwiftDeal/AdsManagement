<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

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
                $socialfb = new SocialFB([
                    "user_id" => $this->user->id,
                    "email" => $email,
                    "fbid" => $fbid, "live" => 1
                ]);
                $socialfb->save();
            }
            $view->set("success", true);
        }
    }

    /**
     * @before _secure
     */
    public function addpage() {
        $this->JSONview();
        $view = $this->getActionView();
        if (RequestMethods::post("can_post") == "true") {
            $fbid = RequestMethods::post("id");
            $fbpage = FBPage::first(["fbid = ?" => $fbid, "user_id = ?" => $this->user->id]);
            if (!$fbpage) {
                $fbpage = new FBPage([
                    "user_id" => $this->user->id,
                    "live" => 1,
                    "fbid" => $fbid
                ]);
            }
            $fbpage->name = RequestMethods::post("name");
            $fbpage->category = RequestMethods::post("category", "");
            $fbpage->likes = RequestMethods::post("likes");
            $fbpage->website = RequestMethods::post("website", "");

            $fbpage->save();
            $view->set("success", true);
        } else {
            $view->set("success", false);
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
            $fbPost = new FBPost([
                "user_id" => $this->user->id,
                "fbpage_id" => $pageid,
                "fbpost_id" => $postid,
                "live" => 1
            ]);

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