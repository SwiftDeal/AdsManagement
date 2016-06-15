<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Manage extends Admin {

    /**
     * @before _secure, changeLayout
     */
    public function logs($action = "", $name = "") {
        $this->seo(array("title" => "Activity Logs", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if ($action == "unlink") {
            $file = APP_PATH ."/logs/". $name . ".txt";
            @unlink($file);
            $this->redirect("/analytics/logs");
        }

        $logs = array();
        $path = APP_PATH . "/logs";
        $iterator = new DirectoryIterator($path);

        foreach ($iterator as $item) {
            if (!$item->isDot()) {
                if (substr($item->getFilename(), 0, 1) != ".") {
                    array_push($logs, $item->getFilename());
                }
            }
        }
        arsort($logs);
        $view->set("logs", $logs);
    }

	/**
     * @before _secure, changeLayout, _admin
     */
    public function customers() {
        $this->seo(array("title" => "Customers Manage", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        $where = array("{$property} = ?" => $value);
        $customers = Customer::all($where, array("id","user_id", "modified", "live", "balance"), "created", "desc", $limit, $page);
        $count = Customer::count($where);

        $view->set("publishers", $publishers);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
        $view->set("property", $property);
        $view->set("value", $value);
    }

    /**
     * @before _secure, _admin
     */
    public function delete($user_id) {
        $this->noview();
        $access = Access::all(array("user_id = ?" => $user_id));
        foreach ($access as $a) {
            $a->delete();
        }

        $advert = Advert::first(array("user_id = ?" => $user_id));
        if ($advert) {
            $advert->delete();
        }

        $banks = Bank::all(array("user_id = ?" => $user_id));
        foreach ($banks as $b) {
            $b->delete();
        }

        $fbpages = FBPage::all(array("user_id = ?" => $user_id));
        foreach ($fbpages as $fbp) {
            $fbp->delete();
        }

        $fbposts = FBPost::all(array("user_id = ?" => $user_id));
        foreach ($fbposts as $fp) {
            $fp->delete();
        }

        $links = Link::all(array("user_id = ?" => $user_id));
        foreach ($links as $link) {
            $stat = Stat::first(array("link_id = ?" => $link->id));
            if ($stat) {
                $stat->delete();
            }
            $link->delete();
        }

        $stats = Stat::first(array("user_id = ?" => $user_id));
        foreach ($stats as $stat) {
            $stat->delete();
        }
        
        $platforms = Platform::all(array("user_id = ?" => $user_id));
        foreach ($platforms as $platform) {
            $platform->delete();
        }

        $publish = Publish::first(array("user_id = ?" => $user_id));
        if ($publish) {
            $publish->delete();
        }

        $transactions = Transaction::all(array("user_id = ?" => $user_id));
        foreach ($transactions as $transaction) {
            $transaction->delete();
        }

        $tickets = Ticket::all(array("user_id = ?" => $user_id));
        foreach ($tickets as $ticket) {
        	$conversations = Conversation::all(array("ticket_id = ?" => $ticket->id));
        	foreach ($conversations as $c) {
        		$c->delete();
        	}
            $ticket->delete();
        }

        $user = User::first(array("id = ?" => $user_id));
        if ($user) {
            $user->delete();
        }
        
        $this->redirect($_SERVER["HTTP_REFERER"]);
    }

    /**
     * @before _secure, _admin
     */
    public function validity($model, $user_id, $live) {
        $this->noview();
        $user = User::first(array("id = ?" => $user_id));
        if ($user) {
            $user->live = $live;
            $user->save();

            switch ($model) {
                case 'publish':
                    $publish = Publish::first(array("user_id = ?" => $user->id));
                    if ($publish) {
                        $publish->live = $live;
                        $publish->save();
                    }
                    switch ($live) {
                        case '0':
                            $this->notify(array(
                                "template" => "accountSuspend",
                                "subject" => "Account Suspended",
                                "user" => $user
                            ));
                            break;
                        
                        case '1':
                            $this->notify(array(
                                "template" => "accountApproved",
                                "subject" => "Account Approved",
                                "user" => $user
                            ));
                            break;
                    }
                    break;
                
                case 'advert':
                    $advert = Advert::first(array("user_id = ?" => $user->id));
                    if ($advert) {
                        $advert->live = $live;
                        $advert->save();
                    }
                    switch ($live) {
                        case '0':
                            $this->notify(array(
                                "template" => "accountSuspend",
                                "subject" => "Account Suspended",
                                "user" => $user
                            ));
                            break;
                        
                        case '1':
                            $this->notify(array(
                                "template" => "accountApproved",
                                "subject" => "Account Approved",
                                "user" => $user
                            ));
                            break;
                    }
                    break;
            }
        }

        $this->redirect($_SERVER["HTTP_REFERER"]);
    }
}
