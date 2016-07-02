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
        $this->seo(array("title" => "Advertize", "view" => $this->getLayoutView()));
        $view = $this->getActionView();$session = Registry::get("session");
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('now')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $customer = $session->get("customer");
        
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set("customer", $customer);
    }
}
