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
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $database = Registry::get("database");
        $paid = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("type=?", "debit")->all();
        $earn = $database->query()->from("transactions", array("SUM(amount)" => "earn"))->where("user_id=?", $this->user->id)->where("type=?", "credit")->all();
        $ticket = Ticket::first(array("user_id = ?" => $this->user->id, "live = ?" => 1), array("subject", "id"), "created", "desc");
        $links = Link::all(array("user_id = ?" => $this->user->id, "live = ?" => true), array("id", "item_id", "short"), "created", "desc", 6, 1);
        
        $total = $database->query()->from("stats", array("SUM(amount)" => "earn", "SUM(click)" => "click"))->where("user_id=?", $this->user->id)->all();
    
        $view->set("total", $total);
        $view->set("paid", abs(round($paid[0]["earn"], 2)));
        $view->set("earn", round($earn[0]["earn"], 2));
        $view->set("links", $links);
        $view->set("ticket", $ticket)
            ->set("payout", Payout::first(array("user_id = ?" => $this->user->id, "live = ?" => 0)))
            ->set("fb", RequestMethods::get("fb", false));
    }
    
    /**
     * Shortens the url for publishers
     * @before _secure, _layout
     */
    public function shortenURL() {
        $this->JSONview();
        $view = $this->getActionView();
        $link = new Link(array(
            "user_id" => $this->user->id,
            "short" => "",
            "item_id" => RequestMethods::get("item"),
            "live" => 1
        ));
        $link->save();
        
        $item = Item::first(array("id = ?" => RequestMethods::get("item")), array("url", "title", "image", "description"));
        $m = Registry::get("MongoDB")->urls;
        $doc = array(
            "link_id" => $link->id,
            "item_id" => RequestMethods::get("item"),
            "user_id" => $this->user->id,
            "url" => $item->url,
            "title" => $item->title,
            "image" => $item->image,
            "description" => $item->description,
            "created" => date('Y-m-d', strtotime("now"))
        );
        $m->insert($doc);

        $d = Meta::first(array("user_id = ?" => $this->user->id, "property = ?" => "domain"), array("value"));
        if($d) {
            $longURL = $d->value . '/' . base64_encode($link->id);
        } else {
            $domains = $this->target();
            $k = array_rand($domains);
            $longURL = RequestMethods::get("domain", $domains[$k]) . '/' . base64_encode($link->id);
        }

        //$link->short = $this->_bitly($longURL);
        $link->short = $longURL;
        $link->save();

        $view->set("shortURL", $link->short);
        $view->set("link", $link);
    }

    /**
     * @before _secure, _layout
     */
    public function links() {
        $this->seo(array("title" => "Stats Charts", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $short = RequestMethods::get("short", "");
        $where = array(
            "short LIKE ?" => "%{$short}%",
            "user_id = ?" => $this->user->id,
            "live = ?" => true
        );

        $links = Link::all($where, array("id", "item_id", "short", "created"), "created", "desc", $limit, $page);
        $count = Link::count($where);

        $view->set("links", $links);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count)
            ->set("fb", RequestMethods::get("fb"));
    }

    protected function target() {
        $session = Registry::get("session");
        $domains = $session->get("domains");

        $alias = array();
        foreach ($domains as $domain) {
            array_push($alias, $domain->value);
        }
        
        return $alias;
    }
}