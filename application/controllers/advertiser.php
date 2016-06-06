<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Advertiser extends Analytics {
    
    /**
     * @before _secure, _layout
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $database = Registry::get("database");
        $paid = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("live=?", 1)->all();
        $earn = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("live=?", 0)->all();

        $ads = Ad::all(array("user_id = ?" => $this->user->id), array("id", "title", "created", "image", "url", "live", "visibility"), "created", "desc", 4, 1);
        
        $view->set("ads", $ads);
        $view->set("paid", round($paid[0]["earn"], 2));
        $view->set("earn", round($earn[0]["earn"], 2));
    }

    /**
     * @before _secure, _layout
     */
    public function stats() {
        $this->seo(array("title" => "Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $insights = Insight::all(array("user_id = ?" => $this->user->id));
        $view->set("insights", $insights);
    }
}
