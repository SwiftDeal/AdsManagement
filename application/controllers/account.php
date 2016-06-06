<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Account extends Auth {
	
	/**
     * @before _secure, _layout
     */
    public function transactions() {
        $this->seo(array("title" => "Transactions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $where = array("user_id = ?" => $this->user->id);

        $transactions = Transaction::all($where);
        $count = Transaction::count($where);
        
        $view->set("transactions", $transactions);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, _layout
     */
    public function platforms() {
        $this->seo(array("title" => "Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

    /**
     * @before _secure, _layout
     */
    public function settings() {
        $this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }
}