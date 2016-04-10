<?php

/**
 * Contains similar code of all models and some helpful methods
 *
 * @author Faizan Ayubi
 */

namespace Shared {

    class Model extends \Framework\Model {

        /**
         * @column
         * @readwrite
         * @primary
         * @type autonumber
         */
        protected $_id;

        /**
         * @column
         * @readwrite
         * @type boolean
         * @index
         */
        protected $_live;

        /**
         * @column
         * @readwrite
         * @type datetime
         */
        protected $_created;

        /**
         * @column
         * @readwrite
         * @type datetime
         */
        protected $_modified;

        /**
         * Every time a row is created these fields should be populated with default values.
         */
        public function save() {
            $primary = $this->getPrimaryColumn();
            $raw = $primary["raw"];
            if (empty($this-> $raw)) {
                $this->setCreated(date("Y-m-d H:i:s"));
                if (!$this->live) {
                    $this->setLive(0);
                }
            }
            $this->setModified(date("Y-m-d H:i:s"));
            parent::save();
        }

        public function mongodb($doc) {
            $collection = \Framework\Registry::get("MongoDB")->clicks;
            $stats = array();$stat = array();
        
            $records = $collection->find($doc);
            if (isset($records)) {
                foreach ($records as $record) {
                    if (isset($stats[$record['country']])) {
                        $stats[$record['country']] += $record['click'];
                    } else {
                        $stats[$record['country']] = $record['click'];
                    }
                }

                foreach ($stats as $key => $value) {
                    array_push($stat, array("country" => $key, "count" => $value));
                }
                
                return $stat;
            }
            return false;
        }

    }

}