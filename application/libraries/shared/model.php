<?php

/**
 * Contains similar code of all models and some helpful methods
 *
 * @author Hemant Mann
 */

namespace Shared {
    use Framework\Registry as Registry;
    use \Shared\Services\Db as Db;

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

        public static function hourly() {
            // override this method to do cron tasks
        }

        public static function displayImg($name = '', $folder = "images") {
            $file = CDN . 'uploads/' . $folder . '/' . $name;
            return $file;
        }

        public function &getMeta() {
            if (property_exists($this, '_meta')) {
                return $this->_meta;
            } else if (property_exists($this, 'meta')) {
                return $this->meta;
            }
            return [];
        }

        /**
         * Converts array of \Shared\Model objects to array of stdClass objects
         * Also converts DateTime class to simple string, pass an extra key in
         * the last parameter to stop this datetime conversion
         * @return array         Array of Objects
         */
        public static function objectArr($arr = [], $fields = [], $opts = []) {
            if (!is_array($arr)) {
                $newArr = [];
                $newArr[] = $arr;
                $arr = $newArr;
            }

            $results = [];
            foreach ($arr as $key => $a) {
                $data = [];
                foreach ($fields as $f) {
                    $convert = $opts['convert'] ?? true;
                    if ($convert && is_object($a->$f) && is_a($a->$f, 'DateTime')) {
                        $data[$f] = $a->$f->format('Y-m-d');
                    } else {
                        $data[$f] = $a->$f;
                    }
                }

                $obj = (object) $data;
                if ($a->_id === $key) {
                    $results[$key] = $obj;
                } else {
                    $results[] = $obj;
                }
            }
            return $results;
        }

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
                
                if ((!is_array($current) && !isset($current)) || is_null($current)) {
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

            if (empty($this->$raw)) {
                if (!array_key_exists('created', $doc)) {
                    $doc['created'] = Db::time();
                }

                $result = $collection->insertOne($doc);
                $this->__id = $result->getInsertedId();
            } else {
                $doc['modified'] = Db::time();

                $this->__id = Utils::mongoObjectId($this->__id);
                $result = $collection->updateOne(['_id' => $this->__id], ['$set' => $doc]);
            }

            // remove BSON Types from class because they prevent it from
            // being serialized and store into the session
            foreach ($columns as $key => $value) {
                $raw = "_{$key}"; $val = $this->$raw;

                if (Db::isType($val, 'id')) {
                    $this->$raw = Utils::getMongoID($val);
                } else if (Db::isType($val, 'date')) {
                    $tz = new \DateTimeZone('Asia/Kolkata');
                    $v = $val->toDateTime();
                    $v->settimezone($tz);
                    $this->$raw = $v;
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
        public function _convertToType($value, $type) {
            if (Db::isType($value, 'regex')) {
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
                    } else if (is_object($value)) {
                        $date = $value;
                        if (Db::isType($value, 'date')) {
                           break;
                        } else if (is_a($value, 'DateTime')) {
                            $date = $value->format('Y-m-d');
                        }
                        $value = Db::time($date);
                    } else {
                        $value = Db::time($value);
                    }
                    break;

                case 'autonumber':
                case 'mongoid':
                    if (Db::isType($value, 'id')) {
                        break;
                    } else if (is_array($value)) {
                        $copy = $value; $value = [];
                        foreach ($copy as $key => $val) {
                            $value[$key] = Utils::mongoObjectId($val);
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
                    $value = $value;
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
        public function _updateQuery($where) {
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

                if (isset($columns[$key])) {
                    $query[$key] = $this->_convertToType($value, $columns[$key]['type']);   
                } else {
                    $query[$key] = $value;
                }
            }
            return $query;
        }

        /**
         * Updates the fields when query mongodb
         * Checks for correct property "id" and "_id"
         * Also accounts for "*" in MySql
         */
        public function _updateFields($fields) {
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

            $opts = Db::opts($fields, $order, $direction, $limit, $page);

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
            $record = null;

            if ($order && $direction) {
                $results = self::_all($where, $fields, $order, $direction, 1);
                
                if (count($results) === 1) {
                    $record = array_shift($results);   
                }
            } else {
                if (count($fields) === 0) {
                    $record = $collection->findOne($where);
                } else {
                    $record = $collection->findOne($where, ['projection' => $fields]);
                }

                $record = $this->_convert($record);    
            }

            return $record;
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
                    if (Db::isType($value, 'id')) {
                        $c->$raw = $this->getMongoID($value);
                    } else if (Db::isType($value, 'date')) {
                        $v = $value->toDateTime();
                        $v->settimezone((new \DateTimeZone('Asia/Kolkata')));
                        $c->$raw = $v;
                    } else if (Db::isType($value, 'document')) {
                        $c->$raw = Utils::toArray($value);
                    } else {    // fallback case
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
