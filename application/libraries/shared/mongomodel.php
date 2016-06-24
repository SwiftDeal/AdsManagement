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
        protected $_types = array("autonumber", "text", "integer", "decimal", "boolean", "datetime", "date", "time", "mongoid", "array");

        /**
         * @column
         * @readwrite
         * @primary
         * @type autonumber
         */
        protected $__id = null;

        /**
         * @column
         * @readwrite
         * @type boolean
         * @index
         */
        protected $_live = null;

        /**
         * @column
         * @readwrite
         * @type datetime
         */
        protected $_created = null;

        /**
         * @column
         * @readwrite
         * @type datetime
         */
        protected $_modified = null;

        /**
         * Every time a row is created these fields should be populated with default values.
         */
        public function save() {
            $primary = $this->getPrimaryColumn();
            $raw = $primary["raw"];
            $collection = $this->getTable();

            $doc = []; $columns = $this->getColumns();
            foreach ($columns as $key => $value) {
                if (isset($this->$value['raw'])) {
                    $doc[$key] = $this->_convertToType($this->$value['raw'], $value['type']);
                }
            }
            if (isset($doc['_id'])) {
                unset($doc['_id']);
            }

            if (empty($this->$raw)) {
                $doc['created'] = new \MongoDate();

                $collection->insert($doc);
                $this->__id = $doc['_id'];
            } else {
                $doc['modified'] = new \MongoDate();
                $collection->update(['_id' => $this->__id], ['$set' => $doc]);
            }
        }

        /**
         * @important | @core function
         * Specific types are needed for MongoDB for proper querying
         * @param misc $value
         * @param string $type
         */
        protected function _convertToType($value, $type) {
            if (is_object($value) && is_a($value, 'MongoRegex')) {
                return $value;
            }

            switch ($type) {
                case 'text':
                    $value = (string) $value;
                    break;

                case 'integer':
                    $value = (int) $value;
                    break;

                case 'boolean':
                    $value = (boolean) $value;
                    break;

                case 'decimal':
                    $value = (float) $value;
                    break;

                case 'datetime':
                case 'date':
                    if (is_object($value) && is_a($value, 'MongoDate')) {
                        break;
                    }
                    $value = new \MongoDate(strtotime($value));
                    break;

                case 'autonumber':
                case 'mongoid':
                    if (is_object($value) && is_a($value, 'MongoId')) {
                        break;
                    } else {
                        $value = new \MongoId($value);
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        $value = (array) $value;   
                    }
                    break;
                
                default:
                    $value = (string) $value;
                    break;
            }
            return $value;
        }

        /**
         * @getter
         * @override
         * @return \MongoCollection
         */
        public function getTable() {
            $table = parent::getTable();
            $collection = Registry::get("MongoDB")->$table;
            return $collection;
        }

        /**
         * @getter
         * Returns "_id" if presents else "__id"
         */
        public function getId() {
            if (property_exists($this, '_id')) {
                return $this->_id;
            }
            return $this->__id;
        }

        /**
         * Updates the MongoDB query
         */
        protected function _updateQuery($where) {
            $columns = $this->getColumns();

            $query = [];
            foreach ($where as $key => $value) {
                $key = str_replace('=', '', $key);
                $key = str_replace('?', '', $key);
                $key = preg_replace("/\s+/", '', $key);

                // because $this->id equivalent to $this->_id
                if ($key == "id" && !property_exists($this, '_id')) {
                    $key = "_id";
                }
                $query[$key] = $this->_convertToType($value, $columns[$key]['type']);
            }
            return $query;
        }

        /**
         * Updates the fields when query mongodb
         * Checks for correct property "id" and "_id"
         * Also accounts for "*" in MySql
         */
        protected function _updateFields($fields) {
            $f = [];
            foreach ($fields as $key => $value) {
                if ($value == "*") {
                    continue;
                }

                if ($value == "id" && !property_exists($this, '_id')) {
                    $f[] = "_id";
                } else {
                    $f[] = $value;
                }
            }
            return $f;
        }

        /**
         * @param array $where ['name' => 'something'] OR ['name = ?' => 'something'] (both works)
         * @param array $fields ['name' => true, '_id' => true]
         * @param string $order Name of the field
         * @param int $direction 1 | -1 OR "asc" |  "desc"
         * @param int $limit
         * @return array
         */
        public static function all($where = array(), $fields = array(), $order = null, $direction = null, $limit = null, $page = null) {
            $model = new static();
            $where = $model->_updateQuery($where);
            $fields = $model->_updateFields($fields);
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
                if ($direction) {
                    switch ($direction) {
                        case 'desc':
                        case 'DESC':
                            $direction = -1;
                            break;
                        
                        case 'asc':
                        case 'ASC':
                            $direction = 1;
                            break;
                    }
                }
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
         * @param array $where ['name' => 'something'] OR ['name = ?' => 'something'] (both works)
         * @param array $fields ['name' => true, '_id' => true]
         * @param string $order Name of the field
         * @param int $direction 1 | -1 OR "asc" |  "desc"
         * @param int $limit
         * @return \Shared\Mongomodel object | null
         */
        public static function first($where = array(), $fields = array()) {
            $model = new static();
            $where = $model->_updateQuery($where);
            $fields = $model->_updateFields($fields);
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

        /**
         * Converts the MongoDB result to an object of class 
         * whose parent is \Shared\MongoModel
         */
        protected function _convert($record) {
            if (!$record) return null;
            $columns = $this->getColumns();

            $class = get_class($this);
            $c = new $class();
            foreach ($columns as $key => $value) {
                $c->$value['raw'] = $record[$key];
            }
            return $c;
        }

        public function delete() {
            $collection = $this->getTable();

            $query = $this->_updateQuery(['_id' => $this->__id]);
            $return = $collection->remove($query, ['justOne' => true]);
            if ($return !== true) {
                throw new \Exception("Error Deleting the record");
            }
        }

        public static function deleteAll($query = []) {
            $instance = new static();
            $query = $instance->_updateQuery($query);
            $collection = $instance->getTable();

            $return = $collection->remove($query);
            if ($return !== true) {
                throw new \Exception("Error in deleteAll");
            }
        }

        public static function count($query = []) {
            $model = new static();
            $query = $model->_updateQuery($query);
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
