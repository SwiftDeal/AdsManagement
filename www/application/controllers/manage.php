<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Manage extends Admin {

	/**
     * @before _secure, changeLayout, _admin
     */
    public function publishers() {
        $this->seo(array("title" => "Publishers Manage", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        $where = array("{$property} = ?" => $value);
        $publishers = Publish::all($where, array("id","user_id", "modified", "live"), "created", "desc", $limit, $page);
        $count = Publish::count($where);

        $view->set("publishers", $publishers);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
        $view->set("property", $property);
        $view->set("value", $value);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function advertisers() {
        $this->seo(array("title" => "Advertisers Manage", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        $where = array("{$property} = ?" => $value);
        $advertisers = Advert::all($where, array("id","user_id", "modified", "live"), "created", "desc", $limit, $page);
        $count = Advert::count($where);

        $view->set("advertisers", $advertisers);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
        $view->set("property", $property);
        $view->set("value", $value);
    }

	/**
     * @before _secure, changeLayout, _admin
     */
    public function news() {
        $this->seo(array("title" => "Member News", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if (RequestMethods::post("news")) {
            $news = new Meta(array(
                "user_id" => $this->user->id,
                "property" => "news",
                "value" => RequestMethods::post("news")
            ));
            $news->save();
            $view->set("message", "News Saved Successfully");
        }
        
        $allnews = Meta::all(array("property = ?" => "news"), array("*"), "created", "desc");
            
        $view->set("allnews", $allnews);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function domains() {
        $this->seo(array("title" => "All Domains", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        switch (RequestMethods::post("action")) {
            case 'addDomain':
                $exist = Meta::first(array("property" => "domain", "value = ?" => RequestMethods::post("domain")));
                if($exist) {
                    $view->set("message", "Domain Exists");
                } else {
                    $domain = new Meta(array(
                        "user_id" => $this->user->id,
                        "property" => "domain",
                        "value" => RequestMethods::post("domain")
                    ));
                    $domain->save();
                    $view->set("message", "Domain Added Successfully");
                }
                break;
            
            case 'assignDomain':
                $domain = new Meta(array(
                    "user_id" => RequestMethods::post("user_id"),
                    "property" => "domain",
                    "value" => RequestMethods::post("domain"),
                    "live" => 1
                ));
                $domain->save();
                $view->set("message", "Domain Added Successfully");
                break;
        }

        $domains = Meta::all(array("property = ?" => "domain"));
        $view->set("domains", $domains);
    }

    /**
     * @before _secure, _admin
     */
    public function delete($user_id) {
        $this->noview();
        $stats = Stat::first(array("user_id = ?" => $user_id));
        foreach ($stats as $stat) {
            $stat->delete();
        }

        $links = Link::all(array("user_id = ?" => $user_id));
        foreach ($links as $link) {
            $stat = Stat::first(array("link_id = ?" => $link->id));
            if ($stat) {
                $stat->delete();
            }
            $link->delete();
        }
        
        $platforms = Platform::all(array("user_id = ?" => $user_id));
        foreach ($platforms as $platform) {
            $platform->delete();
        }

        $access = Access::all(array("user_id = ?" => $user_id));
        foreach ($access as $a) {
            $a->delete();
        }

        $account = Account::first(array("user_id = ?" => $user_id));
        if ($account) {
            $account->delete();
        }

        $advert = Advert::first(array("user_id = ?" => $user_id));
        if ($advert) {
            $advert->delete();
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
