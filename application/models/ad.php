<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
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
        $dq = \Shared\Services\Db::dateQuery(null, $today);

        // find all the ads whose expiry date is today
        $ads = self::all(['expiry' => $dq]);
        foreach ($ads as $a) {
            $a->live = false;   // Make them disabled
            $a->save();
        }
    }

    public static function setCategories($categories = []) {
        $result = [];
        foreach ($categories as $c) {
            if (!is_object($c) || !is_a($c, 'MongoDB\BSON\ObjectID')) {
                $result[] = new \MongoDB\BSON\ObjectID($c);
            }
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

    public function delete() {
        $clickCol = \Framework\Registry::get("MongoDB")->clicks;
        $id = \Shared\Utils::mongoObjectId($this->_id);
        
        $count = \Click::count(['adid' => $id]);
        if ($count !== 0) {
            return ['message' => 'Can not delete!! Campaign contain clicks'];
        }
        @unlink(APP_PATH . '/public/assets/uploads/images/' . $this->image);
        parent::delete();
        $coms = \Commission::all(["ad_id = ?" => $id]);
        foreach ($coms as $com) {
            $com->delete();
        }

        // also need to remove the links
        \Link::deleteAll(['ad_id' => $id]);
        return ['message' => 'Campaign removed successfully!!'];
    }

    public static function earning($opts = [], $clicks) {
        if ($opts['type'] === 'advertiser') {
            $rate = $opts['revenue'];
        } else {
            $rate = $opts['rate'];
        }
        $conversions = (int) ($opts['conversions'] ?? 0);
        $impressions = (int) ($opts['impressions'] ?? 0);

        switch ($opts['campaign']) {
            case 'cpi':
            case 'cpa':
                $revenue = $conversions * $rate;
                break;

            case 'cpm':
                $revenue = $impressions * $rate;
                break;
            
            default:    // cpc
                $revenue = $clicks * $rate;
                break;
        }

        $revenue = round($revenue, 6);

        return [
            'clicks' => $clicks,
            'revenue' => $revenue,
            'conversions' => $conversions,
            'impressions' => $impressions
        ];
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
