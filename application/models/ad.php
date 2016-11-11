<?php

/**
 * All basic info to create an ad
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\ArrayMethods as ArrayMethods;
class Ad extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     *
     * @validate required
     * @label advertiser user id
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @validate required
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate max(255)
     * @label Title
     */
    protected $_title;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @label Description
     */
    protected $_description;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @index
     *
     * @validate required, min(3)
     * @label Url
     */
    protected $_url;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @label to store multi creatives like video, gif, image
     */
    protected $_creative = [];
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(4)
     * @label Image
     */
    protected $_image;

    /**
     * @column
     * @readwrite
     * @type array
     *
     * @validate required
     * @label Category
     * @value Will Contain ObjectId's of Categories
     */
    protected $_category;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     * @label ad type
     * @value article, image, video, native
     */
    protected $_type;

    /**
     * @column
     * @readwrite
     * @type array
     * @index
     */
    protected $_device = [];

    /**
     * @column
     * @readwrite
     * @type date
     */
    protected $_expiry = null;

    public static function hourly() {
        $today = date('Y-m-d');
        $dq = Db::dateQuery(null, $today);

        // find all the ads whose expiry date is today
        $ads = self::all(['expiry' => $dq]);
        foreach ($ads as $a) {
            $a->live = false;   // Make them disabled
            $a->save();
        }
    }

    public static function setCategories($categories = []) {
        try {
            $result = Utils::mongoObjectId($categories);    
        } catch (\Exception $e) {
            // while converting to bson ID - the id may not be valid
            $result = [];
        }
        
        return $result;
    }

    public function getCategories() {
        $result = [];
        foreach ($this->_category as $cat) {
            $result[] = sprintf('%s', $cat);
        }
        return $result;
    }

    public static function displayData($ads = []) {
        $result = []; $ads = (array) $ads;
        foreach ($ads as $a) {
            $a = (object) $a;
            $find = self::first(['_id' => $a->_id], ['title', 'image', 'url']);
            $result[] = [
                '_id' => $a->_id,
                'clicks' => $a->clicks,
                'title' => $find->title,
                'image' => $find->image,
                'url' => $find->url
            ];
        }
        return $result;
    }

    public function validate($opts = [], &$view) {
        if (empty($opts)) {
            return parent::validate();
        }

        $advertisers = $opts['advertisers'];
        $categories = Category::all(['org_id' => $this->org_id], ['_id']);
        if (count($advertisers) === 0 || !in_array($this->user_id, $advertisers)) {
            $this->_errors['user_id'] = ['Invalid User ID passed!!'];
            $view->set([ 'message' => 'Invalid Request' ]);
            return false;
        }

        if (!ArrayMethods::inArray(array_keys($categories), $this->category)) {
            $this->_errors['category'] = ['Invalid Category!!'];
            $view->set([ 'message' => 'Invalid Category!!' ]);
            return false;
        }

        if (!ArrayMethods::inArray($opts['devices'], $this->device)) {
            $this->_errors['device'] = ['Invalid Devices!!'];
            $view->set([ 'message' => 'Invalid Devices!!' ]);
            return false;
        }

        parent::validate();
    }

    /**
     * Overrides the parent delete method to check for clicks on the
     * ad before deleting it
     */
    public function delete() {
        $id = Utils::mongoObjectId($this->_id);
        
        $count = \Click::count(['adid' => $id]);
        if ($count !== 0) {
            return ['message' => 'Can not delete!! Campaign contain clicks', 'success' => false];
        }
        Utils::image($this->image, 'remove');
        parent::delete();
        \Commission::deleteAll(['ad_id' => $id]);
        \Link::deleteAll(['ad_id' => $id]);
        return ['message' => 'Campaign removed successfully!!', 'success' => true];
    }

    /**
     * Calculates the earnings from the AD based on the clicks
     * and the type of campaign - commissions
     * @param  array  $opts   [description]
     * @param  [type] $clicks [description]
     * @return [type]         [description]
     */
    public static function earning($opts = [], $clicks) {
        $extra = $extraRev = 0;
        if ($opts['type'] === 'advertiser') {
            $rate = $opts['revenue'];
        } else if ($opts['type'] === 'both') {
            $extra = $opts['revenue'];
            $rate = $opts['rate'];
        } else {
            $rate = $opts['rate'];
        }
        $conversions = (int) ($opts['conversions'] ?? 0);
        $impressions = (int) ($opts['impressions'] ?? 0);

        switch ($opts['campaign']) {
            case 'cpi':
            case 'cpa':
                $revenue = $conversions * $rate;
                $extraRev = $conversions * $extra;
                break;

            case 'cpm':
                $revenue = $impressions * $rate;
                $extraRev = $impressions * $extra;
                break;
            
            default:    // cpc
                $revenue = $clicks * $rate;
                $extraRev = $clicks * $extra;
                break;
        }

        $revenue = round($revenue, 6); $extraRev = round($extraRev, 6);
        $ans = [
            'clicks' => $clicks,
            'conversions' => $conversions,
            'impressions' => $impressions
        ];

        if ($opts['type'] === 'both') {
            $ans['payout'] = $revenue;
            $ans['revenue'] = $extraRev;
        } else {
            $ans['revenue'] = $revenue;
        }
        return $ans;
    }

    public static function find(&$search, $key, $fields = []) {
        $key = Utils::getMongoID($key);
        if (!array_key_exists($key, $search)) {
            $ad = self::first(['_id' => $key], $fields);
            $search[$key] = $ad;
        } else {
            $ad = $search[$key];
        }
        return $ad;
    }
}
