<?php

/**
 * Platforms only Stat Of any advertiser
 * @author Hemant Mann
 */
class Stat extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @value Platform ID
     */
    protected $_pid;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_impressions = 0;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type array
     */
    protected $_device = [];

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_revenue;

    public static function exists($user, $date) {
        $dateQuery = \Shared\Utils::dateQuery(['start' => $date, 'end' => $date]);
        $perf = self::first([
            'pid' => $user->_id,
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ]);
        if (!$perf) {
            $perf = new self([
                'pid' => $user->_id,
                'impressions' => null,
                'created' => $date,
                'device' => null
            ]);
        }
        $perf->clicks = 0;
        $perf->revenue = 0.00;
        $perf->cpc = 0.00;
        return $perf;
    }
}
