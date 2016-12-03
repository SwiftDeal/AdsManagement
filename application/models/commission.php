<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Services\Db as Db;
use Framework\Registry as Registry;
class Commission extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_ad_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id = null;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id = null;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @label description
     */
    protected $_description;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 3
     *
     * @label model - CPC, CPM etc
     * @validate required, alpha, min(3), max(3)
     */
    protected $_model;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @label Advertiser Charge
     * @validate required
     */
    protected $_revenue = null;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 6,2
     *
     * @label original Rate for model
     * @validate required
     */
    protected $_rate;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @validate required
     * @label Coverage
     */
    protected $_coverage = ['ALL'];

    /**
     * Gets the Rate based on the type of record i.e 'publisher', or 'advertiser'
     * @param  array  $commissions Array of Commissions to search for ad_id
     * @param  String $type        Advertiser | Publisher
     * @return array
     */
    public static function campaignRate($adid, &$commissions = [], $country = null, $extra = []) {        
        $commFetched = $extra['commFetched'] ?? false;
        if ($commFetched) {
            $comm = $commissions;
        } else {
            $comm = self::find($commissions, $adid);
        }
        $info = ['campaign' => 'cpc', 'rate' => 0, 'revenue' => 0, 'type' => $extra['type']];
        if (!is_array($comm)) return $info;

        $commission = (array_key_exists($country, $comm)) ? $comm[$country] : @$comm['ALL']; // because commission might not exists if country is null

        if (!is_object($commission)) {
            return $info;
        }

        $query = [
            'adid' => $adid, 'created' => Db::dateQuery($extra['start'], $extra['end'])
        ];

        switch ($extra['type']) {
            case 'advertiser':
                $info['revenue'] = (float) $commission->revenue;
                break;
            
            case 'publisher':
                $info['rate'] = self::getPubRate($commission, $extra);
                $query['pid'] = $extra['publisher']->_id ?? null;
                break;

            case 'both':
                $info['revenue'] = (float) $commission->revenue;
                $info['rate'] = self::getPubRate($commission, $extra);

                if (isset($extra['publisher'])) {
                    $query['pid'] = $extra['publisher']->_id;
                }
                break;
        }

        switch (strtolower($commission->model)) {
            case 'cpa':
            case 'cpi':
                $count = \Conversion::count($query);
                $info['conversions'] = $count;
                break;

            case 'cpm':
                $info['impressions'] = \Impression::getStats($query);
                break;
            
        }
        $info['campaign'] = strtolower($commission->model);
        return $info;
    }

    protected static function getPubRate($commission, $extra = []) {
        $pub = $extra['publisher'] ?? (object) ['meta' => []];
        $comm = (object) ($pub->meta['campaign'] ?? []);
        // find commission from table - @todo before next cron job
        /*if (isset($pub->_id)) {
            $comms = self::all(['user_id' => $pub->_id], ['model', 'rate', 'coverage']);   
        } else {
            $comms = [];
        }
        $countryWise = self::filter($comms);
        $comm = $countryWise[$commission->country] ?? (object) [];*/

        if (isset($comm->rate) && $comm->model == $commission->model) {
            $rate = (float) $comm->rate;
        } else {
            $rate = (float) $commission->rate;
        }
        return $rate;
    }

    public static function filter($commissions) {
        $countryWise = [];
        foreach ($commissions as $c) {
            $coverage = $c->coverage;

            foreach ($coverage as $country) {
                $countryWise[$country] = (object) [
                    'model' => $c->model,
                    'rate' => $c->rate,
                    'revenue' => $c->revenue,
                    'country' => $country
                ];
            }
        }
        return $countryWise;
    }

    /**
     * Finds the commission based on the "ad_id"
     * @param  array &$search Array of Commission (to prevent querying from database again and again)
     * @param  mixed $key     Object|String representing Ad ID
     */
    public static function find(&$search, $key) {
        $key = \Shared\Utils::getMongoID($key);

        if (!array_key_exists($key, $search)) {
            $commissions = self::all(['ad_id' => $key], ['rate', 'revenue', 'model', 'coverage']);
            $search[$key] = $comm = self::filter($commissions);
        } else {
            $comm = $search[$key];
        }
        return $comm;
    }

    public static function allRate($adid, $user) {
        $payout = '';
        $commissions = self::all(['ad_id' => $adid], ['rate', 'model', 'coverage']);
        $defaultPayout = array_key_exists("campaign", $user->meta);
        foreach ($commissions as $c) {
            $coverage = implode(",", $c->coverage);
            if (strlen($coverage) > 20) {
                $coverage = substr($coverage, 0, 20) . "..";
            }

            if ($defaultPayout) {
                $model = $user->meta["campaign"]["model"];
                $rate = $user->meta["campaign"]["rate"];
                if($c->model == $model) {
                    $payout .= $user->convert($rate)." ".strtoupper($c->model)." ". $coverage;
                } else {
                    $payout .= $user->convert($c->rate)." ".strtoupper($c->model)." ".$coverage;
                }
            } else {
                $payout .= $user->convert($c->rate)." ".strtoupper($c->model)." ".$coverage;
            }
            if (count($commissions) > 1) {
                $payout .= "<br>";
            }
        }
        return $payout;
    }
}
