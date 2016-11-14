<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\RequestMethods as RequestMethods;

/**
 * @property array|object $_meta Contains different keys for storing misc values
 *                               - campaign (object) properties => 'model', 'rate', 'coverage',
 *                               - afields (object) properties => variables based on what set in org
 *                               - tdomain (string) Contains tracking domain for publisher
 *                               - bank (object) properties => 'name', 'ifsc', 'account_no', 'account_owner'
 *                               - payout (object) Types of payout info
 */
class User extends Shared\Model {
    const ROLES = ['afm' => 'Affiliate Manager', 'adm' => 'Advertiser Manager', 'admin' => 'Admin', 'publisher' => 'Publisher', 'advertiser' => 'Advertiser'];

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
     * @label UserName
     */
    protected $_username = null;
    
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
     * @type datetime
     */
    protected $_login = null;

    public static function hourly() {
        $users = self::all(['meta.pixel' => "working"]);
        foreach ($users as $u) {
            $meta = $u->meta;
            unset($meta['pixel']);
            $u->meta = $meta;
            $u->save();
        }
    }

    public function setEmail($email) {
        $e = strtolower($email);
        $e = str_replace(" ", "", $e);
        $this->_email = $e;
    }

    public function convert($n, $p=true, $places = 6, $format=true) {
        // first strip any formatting;
        $n = (0+str_replace(",", "", $n));
        // is this a number?
        if (!is_numeric($n)) return false;
        switch (strtolower($this->currency)) {
            case 'inr':
                $n = (float) ($n * 66);
                $prefix = '<i class="fa fa-inr"></i> ';
                break;

            case 'pkr':
                $n = (float) ($n * 104);
                $prefix = 'Rs. ';
                break;

            case 'aud':
                $n = (float) ($n * 1.3);
                $prefix = '<i class="fa fa-usd"></i> ';
                break;

            case 'eur':
                $n = (float) ($n * 0.9);
                $prefix = '<i class="fa fa-eur"></i> ';
                break;

            case 'gbp':
                $n = (float) ($n * 0.8);
                $prefix = '<i class="fa fa-gbp"></i> ';
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
        if ($num !== false && $format === true) {
            if ($p !== false) $num = $prefix . $num;
            return $num;
        }

        if (is_float($n)) $n = round($n, $places);

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
        $fields = ['name', 'email', 'phone', 'password', 'country'];
        $user = new self([
            'country' => RequestMethods::server("HTTP_CF_IPCOUNTRY", "IN"),
            'username' => RequestMethods::post("name", "USER"),
            'currency' => 'USD', 'org_id' => $org->_id,
            'type' => $type, 'live' => false
        ]);

        foreach ($fields as $f) {
            $user->$f = RequestMethods::post($f, $user->$f);
        }

        if (!$user->validate()) {
            $view->set("errors", $user->errors);
            return false;
        }
        $u = self::first(["email = ?" => $user->email, "org_id = ?" => $org->_id]);
        if ($u) {
            $view->set("message", "User already exists!!");
            $view->set('errors', ['email' => ['Duplicate Email']]);
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

    public function removeFields() {
        $meta = $this->_meta; $afields = $meta['afields'] ?? [];
        foreach ($meta['afields'] as $key => $value) {
            Utils::media($value, 'remove');
        }
    }

    public function delete() {
        $deleteList = ['Link', 'Platform', 'Ad', 'Performance', 'Invoice', 'Adaccess'];
        $query = ['user_id' => $this->_id];

        $delete = false;
        switch ($this->_type) {
            case 'publisher':
                $clicks = Click::count(['pid' => $this->_id]);
                if ($clicks !== 0) {
                    return false;
                }
                $delete = true;

                $this->removeFields();
                break;

            case 'advertiser':
                $ads = Ad::all(['user_id' => $this->_id], ['_id']);
                if (count($ads) === 0) {
                    $delete = true;
                } else {
                    $in = array_keys($ads); $in = Db::convertType($in, 'id');
                    $clickCount = Click::count(['adid' => ['$in' => $in]]);

                    if ($clickCount === 0) {
                        Commission::deleteAll(['ad_id' => ['$in' => $in]]);

                        $delete = true;
                    }
                }
                break;
        }

        if ($delete) {
            parent::delete();
            foreach ($deleteList as $table) {
                $table::deleteAll($query);
            }
        }
        return $delete;
    }
}
