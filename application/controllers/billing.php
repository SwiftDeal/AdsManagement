<?php
/**
 * Controller to manage all billings of publishers and advertisers
 *
 * @author Faizan Ayubi
 */
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Billing extends Admin {

	/**
     * @before _secure
     */
    public function affiliates() {
        $this->seo(array("title" => "Billing"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-60 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery($start, $end);
        $query = ['utype = ?' => 'publisher', 'org_id' => $this->org->_id];
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        if (RequestMethods::get("user_id")) {
            $query['user_id'] = RequestMethods::get("user_id");
        }

        $invoices = \Invoice::all($query);
        $payments = \Payment::all($query);

        $view->set('invoices', $invoices)
            ->set('payments', $payments)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function createinvoice() {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView(); $perfs = [];

        $start = RequestMethods::get("start");
        $end = RequestMethods::get("end");
        $user_id = RequestMethods::get("user_id", null);

        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);

        $dateQuery = Utils::dateQuery($start, $end);
        $query['created'] = Db::dateQuery($start, $end);
        $query['user_id'] = $user_id;
        $view->set("exists", false);

        if ($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $performances = Performance::all($query, ['clicks', 'impressions', 'conversions', 'created', 'revenue'], 'created', 'desc');
            foreach ($performances as $p) {
                $perfs[] = $p;
            }
            $view->set('performances', $perfs);

            $inv_exist = Invoice::exists($user_id, $start, $end);
            if ($inv_exist) {
                $view->set("message", "Invoice already exist for Date range from ".Framework\StringMethods::only_date($inv_exist->start)." to ".Framework\StringMethods::only_date($inv_exist->end));
                $view->set("exists", true);
                return;
            }
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RequestMethods::post("action") == "cinvoice" && RequestMethods::post("amount") > 0) {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "start" => end($perfs)->created,
                "end" => $perfs[0]->created,
                "amount" => RequestMethods::post("amount"),
                "live" => false
            ]);
            $invoice->save();

            $this->redirect("/billing/affiliates.html");
        }
    }

    /**
     * @before _secure
     */
    public function createpayment() {
        $this->seo(array("title" => "Create Payment")); $view = $this->getActionView();
        $user_id = RequestMethods::get("user_id", null); $invs = [];$total = 0;$meta = [];

        if($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $invoices = Invoice::all(['org_id' => $this->org->_id, 'user_id = ?' => $user_id, 'live = ?' => false]);
            foreach ($invoices as $inv) {
                $invs[] = $inv;
            }
            $view->set('invoices', $invs);
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RequestMethods::post("action") == "cpayment") {
            $items = RequestMethods::post("item");
            if ($items) {
                $amounts = RequestMethods::post("amount");
                foreach ($items as $key => $item) {
                    $meta["items"][] = ['name' => $item, 'amount' => $amounts[$key]];
                    $total += $this->currency($amounts[$key]);
                }
            }
            if ($invoices) {
                foreach ($invs as $ia) {
                    if (in_array($ia->id, RequestMethods::post("invoice"))) {
                        $total += $ia->amount;
                        $ia->live = true;
                        $ia->save();
                    }
                }
            }
            $meta["invoices"] = RequestMethods::post("invoice");
            $meta["note"] = RequestMethods::post("note");
            $meta["refer_id"] = RequestMethods::post("refer_id");
            $payment = new Payment([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "type" => RequestMethods::post("type"),
                "amount" => $total,
                "meta" => $meta
            ]);
            $payment->save();

            $this->redirect("/billing/affiliates.html");
        }
        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function advertisers() {
        $this->seo(array("title" => "Billing"));
        $view = $this->getActionView();
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $invoices = \Invoice::all(['utype = ?' => 'advertiser', 'org_id' => $this->org->_id]);

        $view->set('invoices', $invoices)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function raiseinvoice() {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView(); $perfs = [];

        $start = RequestMethods::get("start");
        $end = RequestMethods::get("end");
        $user_id = RequestMethods::get("user_id", null);

        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);

        $dateQuery = Utils::dateQuery($start, $end);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        $query['user_id'] = $user_id;

        if($user_id) {
            $user = \User::first(['type = ?' => 'advertiser', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('advertiser', $user);
            $performances = Performance::all($query, ['clicks', 'impressions', 'conversions', 'created', 'revenue'], 'created', 'desc');
            foreach ($performances as $p) {
                $perfs[] = $p;
            }
            $view->set('performances', $perfs);

            $inv_exist = Invoice::first(["user_id = ?" => $user_id, "start" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]]);
            $inv_exist1 = Invoice::first(["user_id = ?" => $user_id, "end" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]]);

            if ($inv_exist || $inv_exist1) {
                $view->set("message", "Invoice already exist for Date range from ".Framework\StringMethods::only_date($inv_exist->start)." to ".Framework\StringMethods::only_date($inv_exist->end));
                return;
            }
        } else {
            $advertisers = \User::all(['type = ?' => 'advertiser', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('advertisers', $advertisers);
        }

        if (RequestMethods::post("action") == "cinvoice" && RequestMethods::post("amount") > 0) {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "start" => end($perfs)->created,
                "end" => $perfs[0]->created,
                "amount" => RequestMethods::post("amount"),
                "live" => false
            ]);
            $invoice->save();

            $this->redirect("/billing/advertisers.html");
        }
    }
}