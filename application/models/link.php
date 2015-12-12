<?php

/**
 * Description of link
 *
 * @author Faizan Ayubi
 */
use ClusterPoint\DB as DB;
class Link extends Shared\Model {
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     */
    protected $_short;
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_item_id;
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_user_id;

    public static function googl($shortURL) {
        $googl = Framework\Registry::get("googl");
        $object = $googl->analyticsFull($shortURL);
        return $object;
    }

    public function clusterpoint($item_id, $user_id, $time = 0) {
        $clusterpoint = new DB();
        $query = "SELECT * FROM stats WHERE item_id == '{$item_id}' && user_id == '{$user_id}' && timestamp > $time";
        $result = $clusterpoint->index($query);
        return isset($result) ? $result[0] : "";
    }
}
