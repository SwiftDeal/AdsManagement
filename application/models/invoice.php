<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Services\Db as Db;
use Shared\Utils as Utils;
class Invoice extends Shared\Model {

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
     * @type date
     *
     * @validate required
     * @label invoice start date
     */
    protected $_start;

    /**
     * @column
     * @readwrite
     * @type date
     *
     * @validate required
     * @label invoice end date
     */
    protected $_end;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_amount;

    public static function exists($uid, $start = null, $end = null) {
        $uid = Db::convertType($uid);
        $dateQuery = Utils::dateQuery($start, $end);
        $inv_exist = Invoice::first(['$or' => [
            [   // $start, $end exists in b/w invoice start, end
                "user_id" => $uid,
                "start" => ['$lte' => $dateQuery['start'], '$lte' => $dateQuery['end']],
                "end" => ['$gte' => $dateQuery['start'], '$gte' => $dateQuery['end']]
            ], [    // invoice start in b/w $start, $end
                "user_id" => $uid,
                "start" => Db::dateQuery($start, $end),
                "end" => ['$gte' => $dateQuery['start'], '$gte' => $dateQuery['end']]
            ], [    // invoice end exists b/w $start, $end
                "user_id" => $uid,
                "start" => ['$lte' => $dateQuery['start'], '$lte' => $dateQuery['end']],
                "end" => Db::dateQuery($start, $end)
            ], [    // invoice start and end exists b/w $start, $end
                "user_id" => $uid,
                "start" => Db::dateQuery($start, $end),
                "end" => Db::dateQuery($start, $end)
            ]
        ]]);

        return $inv_exist;
    }
}
