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
        $view = $this->getActionView();

        $start = RequestMethods::get("start");
        $end = RequestMethods::get("end");

        $diff = date_diff(new DateTime($start), new DateTime($end));
        $dateQuery = Utils::dateQuery($start, $end);
        $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];

        $user_id = RequestMethods::get("user_id", null);
        $query = [ "user_id" => Utils::mongoObjectId($user_id)];

        if($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $performances = Performance::all($query);
            $view->set('performances', $performances);

            $inv_exist = Invoice::first($query);
            if ($inv_exist) {
                $view->set("message", "Invoice already exist for Date range from ".Framework\StringMethods::only_date($inv_exist->start)." to ".Framework\StringMethods::only_date($inv_exist->end));
                return;
            }
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        $view->set('user_id', $user_id)
            ->set('start', $start)
            ->set('end', $end);

        if (RequestMethods::post("action") == "cinvoice" && RequestMethods::post("amount") > 0) {
            $invoice = new Invoice([
                "org_id" => $this->org->id,
                "user_id" => $user->id,
                "utype" => $user->type,
                "start" => $start,
                "end" => $end,
                "period" => $diff->format("%a"),
                "amount" => RequestMethods::post("amount")
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
        $user_id = RequestMethods::get("user_id", null);

        if($user_id) {
            $user = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $user);
            $invoices = Invoice::all(['org_id' => $this->org->_id, 'user_id = ?' => $user_id]);
            $view->set('invoices', $invoices);
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RequestMethods::post("action") == "cpayment") {
            $meta = [];$items = RequestMethods::post("item");$total = 0;
            if ($items) {
                $amounts = RequestMethods::post("amount");
                foreach ($items as $key => $item) {
                    $meta["items"][] = ['name' => $item, 'amount' => $amounts[$key]];
                    $total += $this->currency($amounts[$key]);
                }
            }
            if ($invoices) {
                foreach ($iamount as $ia) {
                    $total += $ia;
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
}