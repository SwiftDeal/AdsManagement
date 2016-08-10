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
     * @label advertiser user id
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
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
     * @value article, image, video
     */
    protected $_type;

    public static function setCategories($categories = []) {
        $result = [];
        foreach ($categories as $c) {
            if (!is_object($c) || !is_a($c, 'MongoId')) {
                $result[] = new \MongoId($c);
            }
        }
        return $result;
    }

    public static function displayData($ads = []) {
        $result = [];
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
}
