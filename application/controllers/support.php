<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Support extends Auth {

	public function index() {
		$this->seo(array("title" => "Support","view" => $this->getLayoutView()));
        $view = $this->getActionView();
	}

	/**
     * @before _secure, _layout, _admin
     */
	public function tickets() {
		$this->seo(array("title" => "Support Tickets","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $tickets = Ticket::first(array("user_id = ?" => $this->user->id, "live = ?" => false));
        $view->set("tickets", $tickets);
	}
}