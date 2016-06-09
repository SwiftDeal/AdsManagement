<?php

/**
 * Contains similar code of all models and some helpful methods
 *
 * @author Hemant Mann
 */

namespace Shared {
    use Framework\Registry as Registry;

    class MongoModel extends \Framework\Model {
        /**
         * @read
         */
        protected $_types = array("autonumber", "text", "integer", "decimal", "boolean", "datetime", "date", "time", "mongoid");

        /**
         * @column
         * @readwrite
         * @primary
         * @type autonumber
         */
        protected $__id = false;

        /**
         * @column
         * @readwrite
         * @type boolean
         * @index
         */
        protected $_live = true;

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
            $columns = $this->getColumns();
            $this->_modified = new \MongoDate();

            $collection = $this->getTable();

            $doc = [];
            foreach ($columns as $key => $value) {
                $doc[$key] = $this->$value['raw'];
            }
            if (isset($doc['_id'])) {
                unset($doc['_id']);
            }

            if (empty($this->$raw)) {
                $doc['created'] = new \MongoDate();

                $collection->insert($doc);
                $this->_id = $doc['_id'];
            } else {                
                $collection->update(['_id' => $this->_id], ['$set' => $doc]);
            }
        }

        public function getTable() {
            $table = parent::getTable();
            $collection = Registry::get("MongoDB")->$table;
            return $collection;
        }

        /**
         * @param array $where ['name' => 'something']
         * @param array $fields ['name' => true, '_id' => true]
         * @param string $order Name of the field
         * @param int $direction 1 or -1
         * @param int $limit
         * @
         */
        public static function all($where = array(), $fields = array(), $order = null, $direction = null, $limit = null, $page = null) {
            $model = new static();
            return $model->_all($where, $fields, $order, $direction, $limit, $page);
        }

        protected function _all($where = array(), $fields = array(), $order = null, $direction = null, $limit = null, $page = null) {
            $collection = $this->getTable();

            if (empty($fields)) {
                $cursor = $collection->find($where);
            } else {
                $cursor = $collection->find($where, $fields);
            }
            
            if ($order && $direction) {
                $cursor->sort([$order => $direction]);
            }

            if ($page) {
                $cursor->skip($limit * ($page - 1));
            }

            if ($limit) {
                $cursor->limit($limit);
            }

            $results = [];
            foreach ($cursor as $c) {
                $results[] = $this->_convert($c);
            }
            return $results;
        }

        /**
         * @param array $where ['name' => 'something']
         * @param array $fields ['name' => true, '_id' => true]
         * @param string $order Name of the field
         * @param int $direction 1 or -1
         * @param int $limit
         * @
         */
        public static function first($where = array(), $fields = array()) {
            $model = new static();
            return $model->_first($where, $fields);
        }

        protected function _first($where = array(), $fields = array()) {
            $collection = $this->getTable();

            if (empty($fields)) {
                $record = $collection->findOne($where); 
            } else {
                $record = $collection->findOne($where, $fields);
            }

            return $this->_convert($record);
        }

        protected function _convert($record) {
            if (!$record) return null;
            $columns = $this->getColumns();

            $class = get_class($this);
            $c = new $class();
            foreach ($columns as $key => $value) {
                $c->$key = $record[$key];
            }
            return $c;
        }

        public function delete() {
            $collection = $this->getTable();

            $return = $collection->remove(['_id' => $this->_id], ['justOne' => true]);
            if ($return !== true) {
                throw new \Exception("Error Deleting the record");
            }
        }

        public static function deleteAll($query = []) {
            $instance = new static();
            $collection = $instance->getTable();

            $return = $collection->remove($query);
            if ($return !== true) {
                throw new \Exception("Error in deleteAll");
            }
        }

        public static function count($query = []) {
            $model = new static();
            return $model->_count($query);
        }

        protected function _count($query = []) {
            $collection = $this->getTable();

            $count = $collection->count($query);
            return $count;
        }

        /**
         * @param \Shared\Model $model A SQL model
         * @param array $exclude Fields to be excluded from duplicating (optional)
         * Duplicates a SQL Model in MongoDB
         */
        public function duplicate(\Shared\Model $model, $exclude = []) {
            $fields = $model->getColumns();
            if (!empty($exclude)) {
                foreach ($exclude as $key => $value) {
                    unset($fields[$value]);
                }
            }

            foreach ($fields as $key => $value) {
                $this->$key = $model->$key;
            }
            $this->save();
        }
    }
}
