<?php

/**
 * @author Faizan Ayubi
 */
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
     * @type datetime
     */
    protected $_expiry = null;

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
        $com = \Commission::first(["ad_id = ?" => $id]);
        $com->delete();

        // also need to remove the links
        \Link::deleteAll(['ad_id' => $id]);
        return ['message' => 'Campaign removed successfully!!'];
    }

    public static function earning($opts = [], $clicks) {
        $rate = $opts['rate'];
        $conversions = $opts['conversions'];

        if ($conversions === false) {
            $revenue = $rate * $clicks;
        } else { // earning will be based on conversions
            $revenue = $rate * $conversions;
        }

        $revenue = round($revenue, 6);

        return [
            'clicks' => $clicks,
            'revenue' => $revenue,
            'conversions' => (int) $conversions
        ];
    }
}
