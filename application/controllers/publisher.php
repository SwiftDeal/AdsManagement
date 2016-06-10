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
            $adunit = new \Models\Mongo\AdUnit(array(
                "user_id" => $this->user->id,
                "name" => RequestMethods::post("name"),
                "category" => RequestMethods::post("category"),
                "type" => json_encode(RequestMethods::post("type"))
            ));
            $adunit->save();

            if ($adunit->category == "native") {
                $code = '<script>(function (we, a, r, e, vnative){we["vNativeObject"]=vnative;we[vnative]=we[vnative]||function(){(i[vnative].q=i[r].q || []).push(arguments)};var x,y;x=a.createElement(r),y=a.getElementsByTagName(r)[0];x.async=true;x.src=e;y.parentNode.insertBefore(x, y);}(window,document,"script","//serve.vnative.com/native.js","vn"));
                </script>';
                $code .= '<ins class="byvnative"
                            data-client="ca-pub-"'. $this->user->id. '
                            data-slot="'. $adunit->_id .'"
                            data-format="all"></ins>';

                $view->set('code', $code);
            }

            $view->set('adunit', $adunit);
            $view->set('message', "AdUnit was created Successfully!! Go to <a href='/publisher/adunits.html'>AdUnits</a>");
        }
    }

    /**
     * @before _secure, _layout
     */
    public function allowandblockads() {
        $this->seo(array("title" => "Allow and Block Ads", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $adunits = \Models\Mongo\AdsBlocked(array("user_id" => $this->user->id));

        if (RequestMethods::post("action") == "abads") {
            $adunit = new \Models\Mongo\AdsBlocked(array(
                "user_id" => $this->user->id,
                "url" => RequestMethods::post('link')
            ));
            $adunit->save();
            $view->set("message", "Saved Successfully");
        }
    }
}