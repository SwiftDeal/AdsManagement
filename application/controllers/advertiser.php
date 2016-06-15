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
        $now = strftime("%Y-%m-%d", strtotime('now'));
        $customer = $session->get("customer");
        
        $view->set("now", $now);
        $view->set("customer", $customer);
    }
}
