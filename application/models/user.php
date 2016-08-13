<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
class User extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label Name
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     * 
     * @validate required, min(8), max(255)
     * @label Email Address
     */
    protected $_email;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(8), max(100)
     * @label Password
     */
    protected $_password;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 20
     * 
     * @validate max(20)
     * @label phone number
     */
    protected $_phone = null;
    
    /**
    * @column
    * @readwrite
    * @type text
    * @length 5
    */
    protected $_country;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 5
    */
    protected $_currency = "USD";

    /**
    * @column
    * @readwrite
    * @type text
    * @length 5
    *
    * @label admin or publisher
    */
    protected $_type;

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];

    public function getMeta() {
        if (!$this->_meta) {
            $meta = [];
        } else {
            $meta = $this->_meta;
        }
        return $meta;
    }

    public function convert($n, $p=true) {
        // first strip any formatting;
        $n = (0+str_replace(",", "", $n));
        // is this a number?
        if (!is_numeric($n)) return false;
        switch (strtolower($this->currency)) {
            case 'inr':
                $n = (float) ($n * 66);
                $prefix = '<i class="fa fa-inr"></i> ';
                break;
            
            default:
                $prefix = '<i class="fa fa-usd"></i> ';
                break;
        }

        // now filter it;
        $num = false;
        if ($n > 1000000000000) $num = round(($n/1000000000000), 2).'T';
        elseif ($n > 1000000000) $num = round(($n/1000000000), 2).'B';
        elseif ($n > 1000000) $num = round(($n/1000000), 2).'M';
        elseif ($n > 1000) $num = round(($n/1000), 2).'K';
        if ($num !== false) {
            if ($prefix) $num = $prefix . $num;
            return $num;
        }

        if (is_float($n)) $n = number_format($n, 2);
        else $n = number_format($n);

        if ($p !== false) {
            return $prefix . $n;
        }
        return $n;
    }

    public function updatePassword($old, $new) {
        $result = []; $result['errors'] = [];
        $result['message'] = 'Password authorization failed';
        if (sha1($old) !== $this->password) {
            return $result;
        }
        $this->password = $new;

        if ($this->validate()) {
            $this->password = sha1($new);
            $this->save();

            $result['message'] =  'Password updated successfully!!';
        } else {
            $result['message'] = 'Invalid Input';
            $result['errors'] = $this->errors;
        }
        return $result;
    }

    public static function addNew($type, $org, $view) {
        $fields = ['name', 'email', 'password', 'country'];
        $user = new self([
            'country' => 'IN',
            'currency' => 'INR'
        ]);

        foreach ($fields as $f) {
            $user->$f = RequestMethods::post($f, $user->$f);
        }
        $user->org_id = $org->_id;
        $user->type = $type;

        if (!$user->validate()) {
            $view->set("errors", $user->errors);
            return false;
        }
        $u = self::first(["email = ?" => $user->email, "org_id = ?" => $org->_id]);
        if ($u) {
            $view->set("message", "User already exists!!");
            return false;
        }

        return $user;
    }

    public function commission() {
        if (array_key_exists('campaign', $this->meta)) {
            if (array_key_exists('model', $this->meta["campaign"])) {
                return $this->meta["campaign"];
            }
        }
        return false;
    }
}
