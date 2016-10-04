<?php

/**
 * @author Faizan Ayubi
 */
class Impression extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_adid;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_ua;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_device;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_country;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_hits;

    public static function getStats($adid, $pid = null, $dq = []) {
        $query = ['adid' => $adid];
        if ($pid) {
            $query['pid'] = $pid;
        }

        if (count($dq) > 0) {
            $query['created'] = \Shared\Services\Db::dateQuery($dq['start'], $dq['end']);
        }
        $records = self::all($query, ['hits']);
        $total = 0;
        foreach ($records as $r) {
            $total += $r->hits;
        }
        return $total;
    }
}
