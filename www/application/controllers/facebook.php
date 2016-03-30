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
            $socialfb = new SocialFB(array(
                "user_id" => $this->user->id,
                "email" => RequestMethods::post("email"),
                "fbid" => RequestMethods::post("fbid"),
                "live" => 1
            ));
            $socialfb->save();
            $view->set("success", true);
        }
    }

    public function addpage() {
        $this->JSONview();
        $view = $this->getActionView();
        if (RequestMethods::post("can_post") == true) {
            $fbpage = FBPage::first(array("fbid = ?" => RequestMethods::post("id")));
            if ($fbpage) {
                $fbpage->name = RequestMethods::post("name");
                $fbpage->category = RequestMethods::post("category", "");
                $fbpage->likes = RequestMethods::post("likes");
                $fbpage->website = RequestMethods::post("website", "");
            } else {
                $fbpage = new FBPage(array(
                    "user_id" => $this->user->id,
                    "category" => RequestMethods::post("category", ""),
                    "fbid" => RequestMethods::post("id"),
                    "likes" => RequestMethods::post("likes"),
                    "website" => RequestMethods::post("website", ""),
                    "name" => RequestMethods::post("name"),
                    "live" => 1
                ));
            }
            $fbpage->save();
            $view->set("success", true);
        } else {
            $view->set("success", false);
        }
    }
}