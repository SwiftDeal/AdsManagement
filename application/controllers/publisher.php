<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Publisher extends Advertiser {

    /**
     * @before _secure, _layout
     */
    public function index() {
        $this->seo(array("title" => "Monetize", "description" => "Stats for your Data", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $database = Registry::get("database");
        $paid = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("type=?", "debit")->all();
        $earn = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("type=?", "credit")->all();
        $ticket = Ticket::first(array("user_id = ?" => $this->user->id, "live = ?" => 1), array("subject", "id"), "created", "desc");
    
        $view->set("total", "");
        $view->set("paid", abs(round($paid[0]["earn"], 2)));
        $view->set("earn", round($earn[0]["earn"], 2));
    }

    /**
     * @before _secure, _layout
     */
    public function adunits() {
        $this->seo(array("title" => "Ad units", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $short = RequestMethods::get("short", "");
        $where = array("user_id = ?" => $this->user->id);

        $adunits = AdUnit::all($where, array("id", "name", "type", "live", "created"), "created", "desc", $limit, $page);
        $count = AdUnit::count($where);

        $view->set("adunits", $adunits);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, _layout
     */
    public function createadunit() {
        $this->seo(array("title" => "Create Ad unit", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "adunit") {
            $adunit = new \AdUnit(array(
                "user_id" => $this->user->id,
                "name" => RequestMethods::post("name"),
                "category" => RequestMethods::post("category"),
                "type" => json_encode(RequestMethods::post("type"))
            ));
            $adunit->save();

            $mongoad = new \Models\Mongo\AdUnit();
            $mongoad->duplicate($adunit);
        }
    }

    /**
     * @before _secure, _layout
     */
    public function allowandblockads() {
        $this->seo(array("title" => "Allow and Block Ads", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        
    }  
}