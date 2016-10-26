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
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $invoices = \Invoice::all(['utype = ?' => 'publisher', 'org_id' => $this->org->_id]);

        $view->set('invoices', $invoices)
            ->set('start', $start)
            ->set('end', $end);
    }

    /**
     * @before _secure
     */
    public function createinvoice() {
        $this->seo(array("title" => "Create Invoice"));
        $view = $this->getActionView();
        $user_id = RequestMethods::get("user_id", null);
        $start = RequestMethods::get("start");
        $end = RequestMethods::get("end");
        $query = [ "user_id" => Utils::mongoObjectId($user_id)];
        $limit = RequestMethods::get("limit", 10000);
        $page = RequestMethods::get("page", 1);

        if($user_id) {
            $affiliate = \User::first(['type = ?' => 'publisher', 'org_id' => $this->org->_id, 'id = ?' => $user_id]);
            $view->set('affiliate', $affiliate);
            $this->_reportspub($query, $start, $end, $limit, $page);
        } else {
            $affiliates = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id], ['id', 'name']);
            $view->set('affiliates', $affiliates);
        }

        if (RequestMethods::post("action") == "cinvoice") {
            
        }
        $view->set('user_id', $user_id);
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