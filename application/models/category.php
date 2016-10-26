<?php

/**
 * @author Hemant Mann
 */
use Shared\Utils as Utils;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;
class Category extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_name;

    public function setName($name) {
        $this->_name = strtolower($name);
    }

    public function getName() {
        return ucfirst($this->_name);
    }

    /**
     * Adds New AD Categories by checking if that category already exists in 
     * the database to prevent duplicate
     */
    public static function addNew(&$categories, $org, $newCat = []) {
        $result = []; ArrayMethods::copy($categories, $result);
        
        $cat = RequestMethods::post("category") ?? $newCat;
        foreach ($cat as $c) {
            $found = self::first(['name' => strtolower($c), 'org_id' => $org->_id], ['_id', 'name']);
            // remove those which are found
            if ($found) {
                unset($categories[$found->getMongoID()]);
                continue;
            }

            $category = new self([ 'name' => $c, 'org_id' => $org->_id ]);
            $category->save();
            $result[$category->_id] = $category;
        }
        return $result;
    }

    public static function remove(&$categories) {
        $success = true;
        foreach ($categories as $c) {
            if (!$c->inUse()) {
                unset($categories[Utils::getMongoID($c->_id)]);
                $c->delete();
            } else {
                $success = false;
            }
        }
        return $success;
    }

    public static function updateNow($org) {
        // find all the categories in advance
        $categories = self::all(['org_id' => $org->_id], ['name', '_id', 'org_id']);
        self::addNew($categories, $org);

        // Now remove those categories which are removed by the user (i.e not unset in $categories)
        return self::remove($categories);
    }

    public function inUse() {
        $count = Ad::count([
            'org_id' => $this->org_id,
            'category' => ['$elemMatch' => ['$eq' => Utils::mongoObjectId($this->_id)]]
        ]);
        
        if ($count === 0) {
            return false;
        } else {
            return true;
        }
    }
}
