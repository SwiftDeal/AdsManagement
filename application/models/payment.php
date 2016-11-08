<?php

/**
 * @author Faizan Ayubi
 */
class Payment extends Shared\Model {

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
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required
     * @label user type
     * @value publisher or advertiser
     */
    protected $_utype;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required
     * @label payment type
     * @value wire, paypal, paytm etc
     */
    protected $_type = null;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_amount;

    public static function done($user, $dateQuery = []) {
        $query = ['user_id' => $user->_id];
        $both = isset($dateQuery['start']) && isset($dateQuery['end']);
        if ($both) {
            $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        }
        $pays = self::all($query, ['amount']);

        $amount = 0.00;
        foreach ($pays as $p) {
            $amount += $p->amount;
        }
        return [
            'amount' => $amount
        ];
    }
}
