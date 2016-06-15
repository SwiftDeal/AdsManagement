<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use \Curl\Curl;
use Snappy\Pdf;

class Finance extends Admin {

    /**
     * @before _secure, changeLayout, _admin
     */
    public function transactions() {
        $this->seo(array("title" => "Transactions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        $where = array("{$property} = ?" => $value);
        $transactions = Transaction::all($where, array("*"), "created", "desc", $limit, $page);
        $count = Transaction::count($where);

        $view->set("transactions", $transactions);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("property", $property);
        $view->set("value", $value);
    }

    /**
     * @before _secure
     */
    public function credit() {
        $this->JSONview();
        $view = $this->getActionView();
        $configuration = Registry::get("configuration");
        $amount = RequestMethods::post("amount");
        if ($amount < 9999) {
            $view->set("error", "Amount less than minimum amount");
            die();
        }
        if (RequestMethods::post("action") == "credit") {
            $imojo = $configuration->parse("configuration/payment");
            $curl = new Curl();
            $curl->setHeader('X-Api-Key', $imojo->payment->instamojo->key);
            $curl->setHeader('X-Auth-Token', $imojo->payment->instamojo->auth);
            $curl->post('https://www.instamojo.com/api/1.1/payment-requests/', array(
                "purpose" => "Advertisement",
                "amount" => $amount,
                "buyer_name" => $this->user->name,
                "email" => $this->user->email,
                "phone" => $this->user->phone,
                "redirect_url" => "http://vnative.com/finance/success",
                "allow_repeated_payments" => false
            ));

            $payment = $curl->response;
            if ($payment->success == "true") {
                $instamojo = new Instamojo(array(
                    "user_id" => $this->user->id,
                    "payment_request_id" => $payment->payment_request->id,
                    "amount" => $payment->payment_request->amount,
                    "status" => $payment->payment_request->status,
                    "longurl" => $payment->payment_request->longurl,
                    "live" => 0
                ));
                $instamojo->save();
                $view->set("success", true);
                $view->set("payurl", $instamojo->longurl);
            } else {
                $this->redirect("/");
            }
        }
    }

    public function success() {
        $this->seo(array("title" => "Thank You", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $configuration = Registry::get("configuration");
        $payment_request_id = RequestMethods::get("payment_request_id");

        if ($payment_request_id) {
            $instamojo = Instamojo::first(array("payment_request_id = ?" => $payment_request_id));

            if ($instamojo) {
                $imojo = $configuration->parse("configuration/payment");
                $curl = new Curl();
                $curl->setHeader('X-Api-Key', $imojo->payment->instamojo->key);
                $curl->setHeader('X-Auth-Token', $imojo->payment->instamojo->auth);
                $curl->get('https://www.instamojo.com/api/1.1/payment-requests/'.$payment_request_id.'/');
                $payment = $curl->response;

                $instamojo->status = $payment->payment_request->status;
                if ($instamojo->status == "Completed") {
                    $instamojo->live = 1;
                }
                $instamojo->save();

                $user = User::first(array("id = ?" => $instamojo->user_id));

                $transaction = new Transaction(array(
                    "user_id" => $instamojo->user_id,
                    "amount" => $instamojo->amount,
                    "ref" => $instamojo->payment_request_id
                ));
                $transaction->save();

                $advertiser = Advert::first(array("user_id = ?" => $user_id));

                $advertiser->balance += $instamojo->amount;
                $advertiser->save();

                $this->notify(array(
                    "template" => "accountCredited",
                    "subject" => "Payment Received",
                    "user" => $user,
                    "transaction" => $transaction
                ));
            } else {
                $this->redirect("/404");
            }
        }
    }
}
