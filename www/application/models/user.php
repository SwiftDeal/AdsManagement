<?php

/**
 * @author Faizan Ayubi
 */
class User extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type text
     * @length 50
     * @index
     * 
     * @validate required, min(3), max(50)
     * @label username
     */
    protected $_username;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label name
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     * 
     * @validate required, max(255)
     * @label email address
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
     * @label password
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
    protected $_phone;
    
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
    protected $_currency = "INR";

    public function convert($n) {
        // first strip any formatting;
        $n = (0+str_replace(",", "", $n));
        // is this a number?
        if (!is_numeric($n)) return false;
        switch (strtolower($this->currency)) {
            case 'usd':
                $n = (float) ($n / 66);
                $prefix = '<i class="fa fa-usd"></i> ';
                break;
            
            default:
                $prefix = '<i class="fa fa-inr"></i> ';
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

        if ($prefix !== false) {
            return $prefix . $n;
        }
        return $n;
    }
}
