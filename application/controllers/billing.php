<?php
/**
 * Controller to manage all billings of publishers and advertisers
 *
 * @author Faizan Ayubi
 */
use Shared\{Utils, Mail};
use Shared\Services\Db as Db;
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Billing extends Admin {

	/**
     * @before _secure
     */
    public function affiliates() {
        $this->seo(array("title" => "Billing")); $view = $this->getActionView();
        $start = RM::get("start", date("Y-m-d", strtotime('-60 day')));
        $end = RM::get("end", date("Y-m-d", strtotime('now')));

        $query = ['utype' => 'publisher', 'org_id' => $this->org->_id];
        $payments = \Payment::all($query);

        $page = RM::get("page", 1);$limit = RM::get("limit", 10);
        $property = RM::get("property"); $value = RM::get("value");
        if ($property) {
            $query["{$property} = ?"] = $value;
        } else {
            $query['created'] = Db::dateQuery($start, $end);
        }
        $invoices = \Invoice::all($query);

        $view->set('invoices', $invoices)
            ->set('payments', $payments)
            ->set('active', Invoice::count(['utype' => 'publisher', 'org_id' => $this->org->_id, "live = ?" => 1]))
            ->set('inactive', Invoice::count(['utype' => 'publisher', 'org_id' => $this->org->_id, "live = ?" => 0]))
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function createinvoice() {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView(); $perfs = [];

        $start = RM::get("start");
        $end = RM::get("end");
        $user_id = RM::get("user_id", null);

        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);

        $query['created'] = Db::dateQuery($start, $end);
        $query['user_id'] = $user_id;
        $view->set("exists", false);

        if ($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id = ?' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $performances = Performance::all($query, ['clicks', 'impressions', 'conversions', 'created', 'revenue'], 'created', 'desc');
            $perfs = array_values($performances);
            $view->set('performances', $perfs);

            $inv_exist = Invoice::exists($user_id, $start, $end);
            if ($inv_exist) {
                $view->set("message", "Invoice already exist for Date range from " . Framework\StringMethods::only_date($inv_exist->start) . " to " . Framework\StringMethods::only_date($inv_exist->end));
                $view->set("exists", true);
                return;
            }
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RM::post("action") == "cinvoice" && RM::post("amount") > 0) {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "start" => end($perfs)->created,
                "end" => $perfs[0]->created,
                "amount" => RM::post("amount"),
                "live" => false
            ]);
            $invoice->save();

            Registry::get("session")->set('$flashMessage', 'Payment Saved!!');
            $this->redirect("/billing/affiliates.html");
        }
    }

    /**
     * @before _secure
     */
    public function createpayment() {
        $this->seo(array("title" => "Create Payment")); $view = $this->getActionView();
        $user_id = RM::get("user_id", null);
        $invs = []; $total = 0; $meta = [];

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

        if (RM::post("action") == "cpayment") {
            $items = RM::post("item");
            if ($items) {
                $amounts = RM::post("amount");
                foreach ($items as $key => $item) {
                    $meta["items"][] = ['name' => $item, 'amount' => $amounts[$key]];
                    $total += $this->currency($amounts[$key]);
                }
            }
            if ($invoices) {
                foreach ($invs as $ia) {
                    if (in_array($ia->id, RM::post("invoice"))) {
                        $total += $ia->amount;
                        $ia->live = true;
                        $ia->save();
                    }
                }
            }
            $meta["invoices"] = RM::post("invoice");
            $meta["note"] = RM::post("note");
            $meta["refer_id"] = RM::post("refer_id");
            $payment = new Payment([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "type" => RM::post("type"),
                "amount" => $total,
                "meta" => $meta
            ]);
            $payment->save();

            Registry::get("session")->set('$flashMessage', 'Payment Saved!!');
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
        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));

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

        $start = RM::get("start");
        $end = RM::get("end");
        $user_id = RM::get("user_id", null);

        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);

        $dateQuery = Utils::dateQuery($start, $end);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        $query['user_id'] = $user_id;

        if($user_id) {
            $user = \User::first(['type = ?' => 'advertiser', 'org_id = ?' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('advertiser', $user);
            $performances = Performance::all($query, ['clicks', 'impressions', 'conversions', 'created', 'revenue'], 'created', 'desc');
            foreach ($performances as $p) {
                $perfs[] = $p;
            }
            $view->set('performances', $perfs);

            $inv_exist = Invoice::exists($user_id, $start, $end);
            if ($inv_exist) {
                $view->set("message", "Invoice already exist for Date range from ".Framework\StringMethods::only_date($inv_exist->start)." to ".Framework\StringMethods::only_date($inv_exist->end));
                return;
            }
        } else {
            $advertisers = \User::all(['type = ?' => 'advertiser', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('advertisers', $advertisers);
        }

        if (RM::post("action") == "cinvoice" && RM::post("amount") > 0) {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "start" => end($perfs)->created,
                "end" => $perfs[0]->created,
                "amount" => RM::post("amount"),
                "live" => false
            ]);
            $invoice->save();

            Registry::get("session")->set('$flashMessage', 'Payment Saved!!');
            $this->redirect("/billing/advertisers.html");
        }
    }

    /**
     * @before _secure
     */
    public function invoice($id) {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView();
        $invoice = Invoice::first(["live = ?" => false, "org_id = ?" => $this->org->id, "id = ?" => $id]);
        if (RM::get("action") == "delinv") {
            if ($invoice) {
                $invoice->delete();
            }
            Registry::get("session")->set('$flashMessage', 'Invoice Deleted Successfully');
            $this->redirect("/billing/affiliates.html");
        }
        $view->set('i', $invoice);
    }

    /**
     * @before _secure
     */
    public function update($invoice_id) {
        $this->JSONView(); $view = $this->getActionView();
        $i = \Invoice::first(["_id = ?" => $invoice_id, "org_id = ?" => $this->org->_id]);
        if (!$i || RM::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }
        $view->set('message', 'Updated successfully!!');

        $allowedFields = ['live'];
        foreach ($allowedFields as $f) {
            $i->$f = RM::post($f, $i->$f);
        }
        $i->save();
        $view->set('invoice', $i);
    }
}