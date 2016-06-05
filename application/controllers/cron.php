<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Shared\Controller {

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->noview();
        if (php_sapi_name() != 'cli') {
            $this->redirect("/404");
        }
    }

    public function index($type = "daily") {
        $this->log("CRON Started");
        switch ($type) {
            case 'hourly':
                $this->_hourly();
                break;

            case 'daily':
                $this->_daily();
                break;

            case 'weekly':
                $this->_weekly();
                break;

            case 'monthly':
                $this->_monthly();
                break;
        }
    }

    protected function _hourly() {
        // implement
    }

    protected function _daily() {
        // implement
    }

    protected function _weekly() {
        // implement
    }

    protected function _monthly() {
        // implement
    }

    protected function _account($accounts, $ref="linkstracking") {
        foreach ($accounts as $key => $value) {
            $customer = Customer::first(array("user_id = ?" => $key));
            if ($customer) {
                $customer->balance += $value;
                $customer->save();
            } else {
                $this->log("Error: Publisher not found for user - ".$key);
            }
            $transaction = new Transaction(array(
                "user_id" => $key,
                "amount" => $value,
                "ref" => $ref
            ));
            $transaction->save();
        }
    }

    protected function passwordmeta() {
        $now = date('Y-m-d', strtotime("now"));
        $meta = Meta::all(array("property = ?" => "resetpass", "created < ?" => $now));
        foreach ($meta as $m) {
            $m->delete();
        }
    }

}
