<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Account extends Auth {
	
	/**
     * @before _secure, _layout
     */
    public function transactions() {
        $this->seo(array("title" => "Transactions", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $where = array("user_id = ?" => $this->user->id);

        $transactions = Transaction::all($where);
        $count = Transaction::count($where);
        
        $view->set("transactions", $transactions);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, _layout
     */
    public function platforms() {
        $this->seo(array("title" => "Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

    /**
     * @before _secure, _layout
     */
    public function settings() {
        $this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

    /**
     * @before _secure, _layout
     */
    public function profile() {
        $this->seo(array("title" => "Profile", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        switch (RequestMethods::post("action")) {
            case 'saveUser':
                $user = User::first(array("id = ?" => $this->user->id));
                $user->phone = RequestMethods::post("phone");
                $user->name = RequestMethods::post("name");
                $user->username = RequestMethods::post("username");
                $user->currency = RequestMethods::post("currency");
                if ($user->validate()) {
                    $view->set("message", "Saved <strong>Successfully!</strong>");
                    $user->save();
                } else {
                    $view->set("message", "Error see required fields");
                    $view->set("errors", $user->getErrors());
                }
                $view->set("user", $user);
                break;
            case "changePass":
                $user = User::first(array("id = ?" => $this->user->id));
                if ($user->password == sha1(RequestMethods::post("password"))) {
                    $user->password = sha1(RequestMethods::post("npassword"));
                    
                    $user->save();
                    $view->set("message", "Password Changed <strong>Successfully!</strong>");
                } else {
                    $view->set("message", "Incorrect old password entered");
                }
                break;
        }

        switch (RequestMethods::post("action")) {
            case 'addPaypal':
                $paypal = new Paypal(array(
                    "user_id" => $this->user->id,
                    "email" => RequestMethods::post("email")
                ));
                $paypal->save();
                $view->set("message", "Paypal Account Saved <strong>Successfully!</strong>");
                break;
            case 'addPaytm':
                $paytm = new Paytm(array(
                    "user_id" => $this->user->id,
                    "phone" => RequestMethods::post("number")
                ));
                $paytm->save();
                $view->set("message", "Paytm Account Saved <strong>Successfully!</strong>");
                break;
            case 'addBank':
                $bank = new Bank(array(
                    "user_id" => $this->user->id,
                    "name" => RequestMethods::post("name"),
                    "bank" => RequestMethods::post("bank"),
                    "number" => RequestMethods::post("number"),
                    "ifsc" => RequestMethods::post("ifsc"),
                    "pan" => RequestMethods::post("pan")
                ));
                $bank->save();
                $view->set("message", "Bank Account Saved <strong>Successfully!</strong>");
                break;
        }
        $banks = Bank::all(array("user_id = ?" => $this->user->id));
        $paypals = Paypal::all(array("user_id = ?" => $this->user->id), array("email"));
        $paytms = Paytm::all(array("user_id = ?" => $this->user->id), array("phone"));
        
        $view->set("banks", $banks);
        $view->set("paypals", $paypals);
        $view->set("paytms", $paytms);
    }
}