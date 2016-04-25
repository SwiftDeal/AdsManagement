<?php

/**
 * @author Hemant Mann
 */
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class Website extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * @index
     * 
     * @validate required, min(3), max(100)
     * @label Property Name
     */
    protected $_name;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 64
     * @index
     *
     * @validate required, min(5)
     * @label Google Analytics ID
     */
    protected $_gaid;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     * 
     * @validate required, min(3), max(32)
     * @label url
     */
    protected $_url;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_advert_id;

    public function campaign() {
        $collection = Registry::get("MongoDB")->ga_stats;
        
        $records = $collection->find(array("website_id" => (int) $this->id), ['sessions']);
        if (!isset($records)) {
            return 0;
        }

        $sessions = 0;
        foreach ($records as $r) {
            $r = ArrayMethods::toObject($r);
            $sessions += (int) $r->sessions;
        }
        return $sessions;
    }
}
