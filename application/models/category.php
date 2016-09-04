<?php

/**
 * @author Hemant Mann
 */
use Shared\Utils as Utils;
use Framework\Registry as Registry;
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

    public static function updateNow($org) {
        $cat = RequestMethods::post("category");

        // find all the categories in advance
        $records = self::all(['org_id' => $org->_id], ['name', '_id']);
        $categories = [];
        foreach ($records as $r) {
            $categories[$r->getMongoID()] = $r;
        }

        foreach ($cat as $c) {
            $found = self::first(['name' => strtolower($c), 'org_id' => $org->_id], ['_id', 'name']);
            // remove those which are found
            if ($found) {
                unset($categories[$found->getMongoID()]);
                continue;
            }

            $category = new self([
                'name' => $c,
                'org_id' => $org->_id
            ]);
            $category->save();
        }

        // Now remove those categories which are removed by the user
        $success = true;
        foreach ($categories as $c) {
            // check if any AD contains this category
            $adsCol = Registry::get("MongoDB")->ads;
            $ad = $adsCol->findOne([
                'org_id' => $org->_id,
                'category' => ['$elemMatch' => ['$eq' => $c->_id]]
            ]);

            if ($ad) {
                $success = false;
            } else {
                $c->delete();
            }
        }

        if ($success) {
            return 'Categories updated Successfully!!';
        } else {
            return 'Failed to delete some categories because in use by campaigns!!';
        }
    }
}
