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
        $stats = \Framework\Registry::get("MongoDB")->clicks;
        $id = $this->_id;
        $record = $stats->findOne(["adid" => $id]);
        if ($record) {
            return ['message' => 'Can not delete!! Campaign contain clicks'];
        }
        @unlink(APP_PATH . '/public/assets/uploads/images/' . $this->image);
        parent::delete();
        $com = \Commission::first(["ad_id = ?" => $id]);
        $com->delete();
        return ['message' => 'Campaign removed successfully!!'];
    }
}
