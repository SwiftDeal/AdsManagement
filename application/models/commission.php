<?php

/**
 * @author Faizan Ayubi
 */
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
     * @label payout for model
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
    public static function campaignRate($adid, $commissions = [], $org = null, $extra = []) {
        $rate = 0;
        if (!array_key_exists($adid, $commissions)) {
            $comm = self::first(['ad_id' => $adid], ['rate', 'revenue', 'model']);
            $commissions[$adid] = $comm;
        } else {
            $comm = $commissions[$adid];
        }

        $info = ['adsInfo' => $commissions, 'conversions' => false];
        $dateQuery = $extra['dateQuery'];
        $cpaQuery = [
            'adid' => $adid,
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];
        switch ($extra['type']) {
            case 'advertiser':
                $advert = (isset($extra['advertiser'])) ? $extra['advertiser'] : (object) ['meta' => []];
                if ($comm->revenue) {
                    $rate = $comm->revenue;
                } else if (isset($advert->meta['campaign'])) {
                    $rate = $advert->meta['campaign']['rate'];
                } else {
                    $rate = isset($org->meta['rate']) ? $org->meta['rate'] : 0;
                }
                break;
            
            case 'publisher':
                $pub = $extra['publisher'];
                if (isset($pub->meta['campaign']) && !is_null($pub->meta['campaign']['rate'])) {
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
}
