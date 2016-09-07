<?php

/**
 * Contains similar code of all models and some helpful methods
 *
 * @author Hemant Mann
 */

namespace Shared {
    use Framework\Registry as Registry;

    class Model extends \Framework\Model {
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

        public function getMongoID($field = null) {
            if ($field) {
                $id = sprintf('%s', $field);
            } else {
                $id = sprintf('%s', $this->__id);
            }
            return $id;
        }

        /**
         * Every time a row is created these fields should be populated with default values.
         */
        public function save() {
            $primary = $this->getPrimaryColumn();
            $raw = $primary["raw"];
            $collection = $this->getTable();

            $doc = []; $columns = $this->getColumns();
            foreach ($columns as $key => $value) {
                $field = $value['raw'];
                $current = $this->$field;
                
                if (!is_array($current) && !isset($current)) {
                    continue;
                }
                $v = $this->_convertToType($current, $value['type']);
                $v = $this->_preventEmpty($v, $value['type']);
                if (is_null($v)) {
                    continue;
                } else {
                    $doc[$key] = $v;
                }
            }
            if (isset($doc['_id'])) {
                unset($doc['_id']);
            }

            $todayMilli = strtotime('now') * 1000;
            if (empty($this->$raw)) {
                if (!array_key_exists('created', $doc)) {
                    $doc['created'] = new \MongoDB\BSON\UTCDateTime($todayMilli);   
                }

                $result = $collection->insertOne($doc);
                $this->__id = $result->getInsertedId();
            } else {
                $doc['modified'] = new \MongoDB\BSON\UTCDateTime($todayMilli);

                $this->__id = Utils::mongoObjectId($this->__id);
                $result = $collection->updateOne(['_id' => $this->__id], ['$set' => $doc]);
            }

            // remove BSON Types from class because they prevent it from
            // being serialized and store into the session
            foreach ($columns as $key => $value) {
                $raw = "_{$key}"; $val = $this->$raw;

                if (is_object($val)) {
                    if (is_a($val, 'MongoDB\BSON\ObjectID')) {
                        $this->$raw = Utils::getMongoID($val);
                    } else if (is_a($val, 'MongoDB\BSON\UTCDatetime')) {
                        $this->$raw = $val->toDateTime();
                    }
                }
            }
        }

        protected function _preventEmpty($value, $type) {
            switch ($type) {
                case 'integer':
                    if ($value === 0) {
                        $value = null;
                    }
                    break;
                
                case 'array':
                    if (count($value) === 0) {
                        $value = null;
                    }
                    break;

                case 'decimal':
                    if ($value === 0.0) {
                        $value = null;
                    }
                    break;

                case 'text':
                    if ($value === '') {
                        $value = null;
                    }
                    break;
            }
            return $value;
        }

        /**
         * @important | @core function
         * Specific types are needed for MongoDB for proper querying
         * @param misc $value
         * @param string $type
         */
        protected function _convertToType($value, $type) {
            if (is_object($value) && is_a($value, 'MongoDB\BSON\Regex')) {
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
                    if (is_array($value)) {
                        break;
                    }
                    if (is_object($value)) {
                        if (is_a($value, 'MongoDB\BSON\UTCDateTime')) {
                           break;
                        } else if (is_a($value, 'DateTime')) {
                            $value = $value->format('Y-m-d');
                        }
                    }
                    $value = new \MongoDB\BSON\UTCDateTime(strtotime($value) * 1000);
                    break;

                case 'autonumber':
                case 'mongoid':
                    if ((is_object($value) && is_a($value, 'MongoDB\BSON\ObjectID'))) {
                        break;
                    } else if (is_array($value)) {
                        $copy = $value; $value = [];
                        foreach ($copy as $key => $val) {
                            $value[$key] = [];
                            foreach ($val as $v) {
                                $value[$key][] = Utils::mongoObjectId($v);
                            }
                        }
                    } else {
                        $value = Utils::mongoObjectId($value);
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
                if ($value == "*" || !is_string($value)) {
                    continue;
                }

                if ($value == "id" && !property_exists($this, '_id')) {
                    $f["_id"] = 1;
                } else {
                    $f[$value] = 1;
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

            $opts = [];

            if (!empty($fields)) {
                $opts['projection'] = $fields;
            }
            
            if ($order && $direction) {
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
                $opts['sort'] = [$order => $direction];
            }

            if ($page) {
                $opts['skip'] = $limit * ($page - 1);
            }

            if ($limit) {
                $opts['limit'] = (int) $limit;
            }

            $cursor = $collection->find($where, $opts);
            $results = [];
            foreach ($cursor as $c) {
                $converted = $this->_convert($c);
                if ($converted->_id) {
                    $key = Utils::getMongoID($converted->_id);
                    $results[$key] = $converted;
                } else {
                    $results[] = $converted;
                }
            }
            return $results;
        }

        /**
         * @param array $where ['name' => 'something'] OR ['name = ?' => 'something'] (both works)
         * @param array $fields ['name' => true, '_id' => true]
         * @param string $order Name of the field
         * @param int $direction 1 | -1 OR "asc" |  "desc"
         * @param int $limit
         * @return \Shared\Model object | null
         */
        public static function first($where = array(), $fields = array(), $order = null, $direction = null) {
            $model = new static();
            $where = $model->_updateQuery($where);
            $fields = $model->_updateFields($fields);
            return $model->_first($where, $fields, $order, $direction);
        }

        protected function _first($where = array(), $fields = array(), $order = null, $direction = null) {
            $collection = $this->getTable();

            if ($order && $direction) {
                switch ($direction) {
                    case 'desc':
                    case 'DESC':
                        $direction = -1;
                        break;
                    
                    case 'asc':
                    case 'ASC':
                        $direction = 1;
                        break;

                    default:
                        $direction = 1;
                        break;
                }
                $cursor = $collection->find($where, [
                    'projection' => $fields,
                    'sort' => [$order => $direction],
                    'limit' => 1
                ]);

                $record = [];
                foreach ($cursor as $c) {
                    $record = $c;
                }
            } else {
                if (count($fields) === 0) {
                    $record = $collection->findOne($where);
                } else {
                    $record = $collection->findOne($where, ['projection' => $fields]);
                }
            }

            return $this->_convert($record);
        }

        /**
         * Converts the MongoDB result to an object of class 
         * whose parent is \Shared\Model
         */
        protected function _convert($record) {
            if (!$record) return null;
            $columns = $this->getColumns();
            $record = (array) $record;

            $class = get_class($this);
            $c = new $class();

            foreach ($record as $key => $value) {
                if (!property_exists($this, "_{$key}")) {
                    continue;
                }
                $raw = "_{$key}";

                if (is_object($value)) {
                    if (is_a($value, 'MongoDB\BSON\ObjectID')) {
                        $c->$raw = $this->getMongoID($value);
                    } else if (is_a($value, 'MongoDB\BSON\UTCDatetime')) {
                        $c->$raw = $value->toDateTime();
                    } else if (is_a($value, 'MongoDB\Model\BSONArray') || is_a($value, 'MongoDB\Model\BSONDocument')) {    // fallback case
                        $c->$raw = Utils::toArray($value);
                    } else {
                        $c->$raw = (object) $value;
                    }
                } else {
                    $c->$raw = $value;
                }
            }
            
            return $c;
        }

        /**
         * Find the records of the table and if none found then sets a
         * flash message to the session and redirects if $opts['redirect']
         * is set else returns the records
         */
        public static function isEmpty($query = [], $fields = [], $opts = []) {
            $records = self::all($query, $fields);
            $session = Registry::get("session");

            if (count($records) === 0) {
                if (isset($opts['msg'])) {
                    $session->set('$flashMessage', $opts['msg']);
                }

                if (isset($opts['redirect'])) {
                    $controller = $opts['controller'];
                    $controller->redirect($opts['redirect']);
                }
            }
            return $records;
        }

        public function delete() {
            $collection = $this->getTable();

            $query = $this->_updateQuery(['_id' => $this->__id]);
            $return = $collection->deleteOne($query);
        }

        public static function deleteAll($query = []) {
            $instance = new static();
            $query = $instance->_updateQuery($query);
            $collection = $instance->getTable();

            $return = $collection->deleteMany($query);
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
    }
}
