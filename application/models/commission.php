<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Services\Db as Db;
class Commission extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @validate required
     */
    protected $_ad_id;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
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
    protected $_coverage;

    /**
     * Gets the Rate based on the type of record i.e 'publisher', or 'advertiser'
     * @param  array  $commissions Array of Commissions to search for ad_id
     * @param  String $type        Advertiser | Publisher
     * @return array
     */
    public static function campaignRate($adid, &$commissions = [], $country = null, $extra = []) {        
        $comm = self::find($commissions, $adid);
        $info = ['campaign' => 'cpc', 'rate' => 0, 'revenue' => 0, 'type' => $extra['type']];
        if (!is_array($comm)) return $info;

        $commission = (array_key_exists($country, $comm)) ? $comm[$country] : $comm['ALL']; // because commission might not exists if country is null

        if (array_key_exists($country, $comm)) {
            var_dump($comm);
        }

        if (!is_object($commission)) {
            throw new \Exception('Invalid Commission!');
        }

        $query = [
            'adid' => $adid, 'created' => Db::dateQuery($extra['start'], $extra['end'])
        ];

        switch ($extra['type']) {
            case 'advertiser':
                $info['revenue'] = (float) $commission->revenue;
                break;
            
            case 'publisher':
                $pub = $extra['publisher'];
                $info['rate'] = (float) $commission->rate;
                $query['pid'] = $pub->_id;
                break;
        }

        switch (strtolower($commission->model)) {
            case 'cpa':
                $count = \Conversion::count($query);
                $info['conversions'] = $count;
                break;

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

    public static function find(&$search, $key) {
        $key = \Shared\Utils::getMongoID($key);

        $countryWise = [];
        if (!array_key_exists($key, $search)) {
            $commissions = self::all(['ad_id' => $key], ['rate', 'revenue', 'model', 'coverage']);
            foreach ($commissions as $c) {
                $coverage = $c->coverage;

                foreach ($coverage as $country) {
                    $countryWise[$country] = (object) [
                        'model' => $c->model,
                        'rate' => $c->rate,
                        'revenue' => $c->revenue
                    ];
                }
            }
            $search[$key] = $countryWise;
            $comm = $countryWise;
        } else {
            $comm = $search[$key];
        }
        return $comm;
    }
}
