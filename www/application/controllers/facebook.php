<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Facebook extends Auth {

    public function fblogin() {
        $this->JSON
        $exist = User::first(array("email = ?" => RequestMethods::post("email")));
        if($exist) {
            if($exist->password == sha1(RequestMethods::post("password"))) {
                if ($exist->live) {
                    return $this->authorize($exist);
                } else {
                    return "User account not verified";
                }
            } else{
                return 'Wrong Password, Try again or <a href="/auth/forgotpassword.html">Reset Password</a>';
            }
            
        } else {
            return 'User doesnot exist. Please signup <a href="/publisher/register.html">here</a>';
        }
    }
}