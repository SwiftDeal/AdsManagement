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
        $where = array("user_id = ?" => $this->user->_id);

        $transactions = Transaction::all($where, array("id", "ref", "amount", "live", "created"), "created", -1, $limit, $page);
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

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $where = array("user_id = ?" => $this->user->_id);

        $platforms = Platform::all($where, array("url", "type", "category", "live", "created"), "created", -1, $limit, $page);
        $count = Platform::count($where);
        
        $view->set("platforms", $platforms);
        $view->set("limit", $limit);
        $view->set("page", $page);
        $view->set("count", $count);
    }

    /**
     * @before _secure, _layout
     */
    public function settings() {
        $this->seo(array("title" => "Profile", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $user = User::first(array("id = ?" => $this->user->_id));
        $payouts = Payout::all(array("user_id = ?" => $this->user->_id));

        switch (RequestMethods::post("action")) {
            case 'saveUser':
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
                if ($user->password == sha1(RequestMethods::post("password"))) {
                    $user->password = sha1(RequestMethods::post("npassword"));
                    
                    $user->save();
                    $view->set("message", "Password Changed <strong>Successfully!</strong>");
                } else {
                    $view->set("message", "Incorrect old password entered");
                }
                break;
            case 'payout':
                $payout = new Payout(array(
                    "user_id" => $this->user->_id,
                    "type" => RequestMethods::post("type"),
                    "account" => RequestMethods::post("account"),
                    "meta" => json_encode(RequestMethods::post("meta", ""))
                ));
                $payout->save();
                break;
        }

        $view->set("payouts", $payouts);
        $view->set("user", $user);
    }
}