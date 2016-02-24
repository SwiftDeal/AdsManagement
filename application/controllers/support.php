<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Support extends Publisher {

	public function index() {
		$this->seo(array("title" => "Support","view" => $this->getLayoutView()));
        $view = $this->getActionView();
	}

	/**
     * @before _secure, _layout
     */
	public function tickets() {
		$this->seo(array("title" => "Support Tickets","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $tickets = Ticket::all(array("user_id = ?" => $this->user->id, "live = ?" => true));
        $view->set("tickets", $tickets);
	}

	/**
     * @before _secure, _layout
     */
	public function create() {
		$this->seo(array("title" => "Create Ticket","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post("action") == "ticket") {
        	$ticket = new Ticket(array(
        		"user_id" => $this->user->id,
        		"subject" => RequestMethods::post("subject"),
        		"live" => true
        	));
        	if($ticket->validate()) {
        		$ticket->save();
        		$conversation = new Conversation(array(
        			"user_id" => $this->user->id,
        			"ticket_id" => $ticket->id,
        			"message" => RequestMethods::post("message"),
        			"file" => $this->_upload("file", "files"),
        		));
                if ($conversation->validate()) {
                    $view->set("message", "Thank You, we will reply within 24 hours.");
                    $conversation->save();
                } else {
                    $view->set("errors", $conversation->getErrors());
                }
        	} else {
                $view->set("errors", $ticket->getErrors());
            }
        }

        
        $view->set("tickets", $tickets);
	}

    public function _layout() {
        $session = Registry::get("session");
        
        $publish = $session->get("publish");
        if (isset($publish)) {
            $this->_publish = $publish;
            $this->defaultLayout = "layouts/publisher";
            $this->setLayout();
        }

        $advert = $session->get("advert");
        if (isset($advert)) {
            $this->_advert = $advert;
            $this->defaultLayout = "layouts/advertiser";
            $this->setLayout();
        }

        if (!isset($publish) && !isset($advert)) {
            self::redirect("/index.html");
        }
    }
}