<?php

/**
 * Description of analytics
 *
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;

class Finance extends Admin {

    /**
     * All earnings records of persons
     * 1 - unpaid, 0 - paid
     * 
     * @before _secure, changeLayout, _admin
     */
    public function records() {
        $this->seo(array("title" => "Records Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $accounts = array();
        $startdate = RequestMethods::get("startdate", date('Y-m-d', strtotime("-7 day")));
        $enddate = RequestMethods::get("enddate", date('Y-m-d', strtotime("now")));
        $live = RequestMethods::get("live", 1);
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $offset = ($page - 1) * $limit;

        $database = Registry::get("database");
        $result = $database->execute("SELECT user_id, SUM(amount) as earn FROM earnings WHERE live = {$live} GROUP BY user_id ORDER BY earn DESC LIMIT {$offset},{$limit}");

        $accounts = array();
        for ($i = 0; $i < $result->num_rows; $i++) {
            $data = $result->fetch_array(MYSQLI_ASSOC);
            array_push($accounts, \Framework\ArrayMethods::toObject(array(
                "user_id" => $data["user_id"],
                "amount" => $data["earn"]
            )));
        }

        $view->set("accounts", $accounts);
        $view->set("count", $count);
        $view->set("page", $page);
        $view->set("limit", $limit);
        $view->set("live", $live);
    }

    /**
     * Finds the earning from a website
     * @before _secure, changeLayout, _admin
     */
    public function earnings() {
        $this->seo(array("title" => "Earnings Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $startdate = RequestMethods::get("startdate", date('Y-m-d', strtotime("-7 day")));
        $enddate = RequestMethods::get("enddate", date('Y-m-d', strtotime("now")));
        $website = RequestMethods::get("website", "http://www.khattimithi.com");

        $amount = 0;
        $where = array(
            "url LIKE ?" => "%{$website}%",
            "created >= ?" => $this->changeDate($startdate, "-1"),
            "created <= ?" => $this->changeDate($enddate, "1")
        );
        $items = Item::all($where, array("id"));
        $count = Item::count($where);

        foreach ($items as $item) {
            $earnings = Earning::all(array("item_id = ?" => $item->id), array("amount"));
            foreach ($earnings as $earning) {
                $amount += $earning->amount;
            }
        }
        
        $view->set("startdate", $startdate);
        $view->set("enddate", $enddate);
        $view->set("items", $items);
        $view->set("count", $count);
        $view->set("website", $website);
        $view->set("amount", $amount);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function makepayment($user_id) {
        $this->seo(array("title" => "Make Payment", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $database = Registry::get("database");
        $amount = $database->query()
            ->from("earnings", array("SUM(amount)" => "earn"))
            ->where("user_id=?",$user_id)
            ->where("live=?",1)
            ->all();

        if (RequestMethods::post("action") == "payment") {
            $earnings = Earning::all(array("user_id = ?" => $user_id, "live = ?" => 1));
            foreach ($earnings as $earning) {
                $earning->live = null;
                $earning->save();
            }
            $payment = new Payment(array(
                "user_id" => $user_id,
                "amount" => round($amount[0]["earn"], 2),
                "mode" => RequestMethods::post("mode"),
                "ref_id" => RequestMethods::post("ref_id"),
                "live" => 1
            ));

            $payment->save();

            self::redirect("/finance/records");
        }

        $payee = User::first(array("id = ?" => $user_id));
        $account = Account::first(array("user_id = ?" => $user_id));

        $view->set("payee", $payee);
        $view->set("account", $account);
        $view->set("amount", $amount[0]["earn"]);
    }

    /**
     * Earning on a Content
     * @before _secure, changeLayout, _admin
     */
    public function content($id='') {
        $this->seo(array("title" => "Content Finance", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $item = Item::first(array("id = ?" => $id));

        $earn = 0;
        $earnings = Earning::all(array("item_id = ?" => $item->id), array("amount"));
        foreach ($earnings as $earning) {
            $earn += $earning->amount;
        }

        $links = Link::count(array("item_id = ?" => $item->id));
        $rpm = RPM::count(array("item_id = ?" => $item->id));

        $view->set("item", $item);
        $view->set("earn", $earn);
        $view->set("links", $links);
        $view->set("rpm", $rpm);
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function payments() {
        $this->seo(array("title" => "Payments", "view" => $this->getLayoutView()));

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $id = RequestMethods::get("id");
        if (!empty($id)) {
            $where = array(
                "user_id = ?" => $id
            );
        } else{
            $where = array();
        }

        $view = $this->getActionView();
        $payments = Payment::all($where, array("*"), "created", "desc", $limit, $page);
        $count = Payment::count($where);

        $view->set("payments", $payments);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("id", $id);
    }
    
}
