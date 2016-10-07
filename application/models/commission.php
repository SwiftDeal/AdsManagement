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
    public static function campaignRate($adid, &$commissions = [], $org = null, $extra = []) {        
        $comm = self::find($commissions, $adid); $rate = 0;
        $info = ['conversions' => false, 'rate' => $rate];
        if (!is_object($comm)) return $info;

        $cpaQuery = [
            'adid' => $adid, 'created' => Db::dateQuery($extra['start'], $extra['end'])
        ];
        switch ($extra['type']) {
            case 'advertiser':
                $advert = (isset($extra['advertiser'])) ? $extra['advertiser'] : (object) ['meta' => []];
                if ($comm->revenue) {
                    $rate = $comm->revenue;
                } else if (isset($advert->meta['campaign']) && $advert->meta['campaign']['model'] == 'cpc') {
                    $rate = $advert->meta['campaign']['rate'];
                } else {
                    $rate = isset($org->meta['rate']) ? $org->meta['rate'] : 0;
                }
                break;
            
            case 'publisher':
                $pub = $extra['publisher'];
                if (isset($pub->meta['campaign']) && $pub->meta['campaign']['model'] == 'cpc' && !is_null($pub->meta['campaign']['rate'])) {
                    $rate = $pub->meta['campaign']['rate'];
                } else {
                    $rate = $comm->rate;
                }
                $cpaQuery['pid'] = $pub->_id;
                break;
        }

        switch (strtolower($comm->model)) {
            case 'cpa':
                $count = \Conversion::count($cpaQuery);
                $info['conversions'] = $count;
                break;
            
        }
        $rate = (float) $rate; $info['rate'] = $rate;
        return $info;
    }

    public static function find(&$search, $key) {
        $key = \Shared\Utils::getMongoID($key);
        if (!array_key_exists($key, $search)) {
            $comm = self::first(['ad_id' => $key], ['rate', 'revenue', 'model', 'coverage']);
            $search[$key] = $comm;
        } else {
            $comm = $search[$key];
        }
        return $comm;
    }
}
