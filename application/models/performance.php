<?php

/**
 * Performnace of Campaigns, advertisers, publishers, Contest
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\ArrayMethods as ArrayMethods;
class Performance extends Shared\Model {

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
     * @type mongoid
     * @index
     */
    protected $_adid = null;

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
    protected $_clicks = 0;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_conversions = 0;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_revenue = 0.0;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_profit = 0.0;

    public function save() {
        if ($this->clicks === 0 && $this->impressions === 0 && $this->conversions === 0) {
            return false;
        }
        parent::save();
    }

    public static function calculate($user, $dateQuery = []) {
        $query = ['user_id' => $user->_id];
        $both = isset($dateQuery['start']) && isset($dateQuery['end']);
        if ($both) {
            $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        }
        $perf = self::all($query, ['clicks', 'revenue', 'impressions', 'conversions']);

        $clicks = 0; $revenue = 0.00; $impressions = 0; $conversions = 0;
        foreach ($perf as $p) {
            $clicks += $p->clicks;
            $revenue += $p->revenue;
            $impressions += $p->impressions;
            $conversions += $p->conversions;
        }
        return [
            'clicks' => $clicks,
            'revenue' => $revenue,
            'impressions' => $impressions,
            'conversions' => $conversions
        ];
    }

    public static function exists($user, $date) {
        $perf = self::first([
            'user_id' => $user->_id,
            'created' => \Shared\Services\Db::dateQuery($date, $date)
        ]);
        if (!$perf) {
            $perf = new self([
                'user_id' => $user->_id,
                'created' => $date
            ]);
        }
        $perf->clicks = $perf->impressions = $perf->conversions = 0;
        $perf->revenue = $perf->cpc = $perf->profit = 0.00;
        $perf->meta = [];
        return $perf;
    }

    public function update($data = []) {
        foreach ($data as $key => $value) {
            try {
                $this->$key += $value;
            } catch (\Exception $e) {
                continue;
            }
        }
        $this->calAvgCpc();
    }

    public function calAvgCpc() {
        if ($this->clicks === 0) {
            $avgCpc = 0.00;
        } else {
            $avgCpc = $this->revenue / $this->clicks;
        }

        $this->cpc = round($avgCpc, 6);
    }

    public static function calProfit($pubPerf, $advPerf) {
        $pubPerf = (array) $pubPerf; $advPerf = (array) $advPerf;
        $payout = $pubPerf['revenue']; $rev = $advPerf['revenue'];
        $pubPerf['payout'] = $payout;
        $pubPerf['revenue'] = $rev;
        $pubPerf['profit'] = $rev - $payout;

        return (object) $pubPerf;
    }

    public static function total($dq = [], $user = null, $fields = []) {
        $name = __CLASS__;

        if (count($fields) === 0) {
            $fields = ['clicks', 'conversions', 'impressions', 'revenue'];
        }
        $project = ['user_id' => 1, '_id' => 0]; $group = ['_id' => '$user_id'];
        foreach ($fields as $f) {
            $project[$f] = 1;
            $group[$f] = ['$sum' => '$' . $f];
        }

        $match = [ 'created' => Db::dateQuery($dq['start'], $dq['end']) ];
        if (is_array($user)) {
            $keys = ArrayMethods::arrayKeys($user, '_id');
            $match['user_id'] = ['$in' => Db::convertType($keys)];
        } else {
            $match['user_id'] = Db::convertType($user->_id);
        }
        $records = Db::collection($name)->aggregate([
            ['$match' => $match],
            ['$project' => $project],
            ['$group' => $group]
        ]);

        $result = [];
        foreach ($records as $r) {
            $obj = Utils::toArray($r); $add = [];
            foreach ($fields as $f) {
                $add[$f] = $obj[$f];
            }
            ArrayMethods::add($add, $result);
        }
        return $result;
    }

    public static function overall($dateQuery = [], $user=null) {
        $q = [];$clicks = []; $conversions = []; $impressions = []; $payouts = []; 
        $total_clicks = 0; $total_conversions = 0; $total_payouts = 0; $total_impressions = 0;

        if (is_array($user)) {
            $in = ArrayMethods::arrayKeys($user, '_id');
            $q["user_id"] = ['$in' => $in];
        } elseif ($user) {
            $q["user_id"] = $user->id;
        }

        if (count($dateQuery) > 0) {
            $q["created"] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];   
        }
        $performances = self::all($q, ['revenue', 'clicks', 'created', 'impressions', 'conversions']);
        foreach ($performances as $p) {
            //calculating datewise
            $date = $p->created->format('Y-m-d');

            $total_clicks += $p->clicks;
            ArrayMethods::counter($clicks, $date, $p->clicks);

            $total_conversions += $p->conversions;
            ArrayMethods::counter($conversions, $date, $p->conversions);

            $total_impressions += $p->impressions;
            ArrayMethods::counter($impressions, $date, $p->impressions);

            $total_payouts += $p->revenue;
            ArrayMethods::counter($payouts, $date, $p->revenue);
        }

        ksort($clicks); ksort($impressions); ksort($payouts);
        $clicks = Utils::dateArray($clicks);
        $impressions = Utils::dateArray($impressions);
        $payouts = Utils::dateArray($payouts);

        return [
            "impressions" => $impressions,
            "total_impressions" => $total_impressions,
            "clicks" => $clicks,
            "total_clicks" => $total_clicks,
            "conversions" => $conversions,
            "total_conversions" => $total_conversions,
            "payouts" => $payouts,
            "total_payouts" => $total_payouts
        ];
    }
}
