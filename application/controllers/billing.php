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
}