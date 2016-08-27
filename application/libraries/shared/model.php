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
                $id = $field->{'$id'};
            } else {
                $id = $this->_id->{'$id'};
            }
            return $id;
        }

        /**
         * Renders the form fields for different properties of a model
         * with validations and type which can be looped through in the views
         */
        public function render($f = []) {
            $fields = array(); $count = count($f);
            foreach ($this->columns as $column) {
                if (!$column["label"]) {
                    continue;
                }
                if ($count != 0 && !in_array($column["name"], $f)) {
                    continue;
                }

                $r = Utils::particularFields($column["name"]);
                $arr = array(
                    "name" => $column["name"],
                    "placeholder" => $column["label"],
                    "type" => $r["type"]
                );
                if ($column["validate"]) {
                    $v = Utils::parseValidations($column["validate"]);
                    $arr = array_merge($arr, $v);
                }
                $fields[$arr['name']] = $arr;
            }
            return $fields;
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
                if (isset($this->$value['raw'])) {
                    $v = $this->_convertToType($this->$value['raw'], $value['type']);
                    $v = $this->_preventEmpty($v, $value['type']);
                    if (is_null($v)) {
                        continue;
                    } else {
                        $doc[$key] = $v;
                    }
                }
            }
            if (isset($doc['_id'])) {
                unset($doc['_id']);
            }

            if (empty($this->$raw)) {
                if (!array_key_exists('created', $doc)) {
                    $doc['created'] = new \MongoDate();   
                }

                $collection->insert($doc);
                $this->__id = $doc['_id'];
            } else {
                $doc['modified'] = new \MongoDate();
                $collection->update(['_id' => $this->__id], ['$set' => $doc]);
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
                    if ((is_object($value) && is_a($value, 'MongoDate')) || is_array($value)) {
                        break;
                    }
                    $value = new \MongoDate(strtotime($value));
                    break;

                case 'autonumber':
                case 'mongoid':
                    if ((is_object($value) && is_a($value, 'MongoId')) || is_array($value)) {
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

        protected function _first($where = array(), $fields = array(), $order, $direction) {
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
                $cursor = $collection->find($where, $fields)->sort([$order => $direction])->limit(1);

                $record = [];
                foreach ($cursor as $c) {
                    $record = $c;
                }
            } else {
                if (empty($fields)) {
                    $record = $collection->findOne($where); 
                } else {
                    $record = $collection->findOne($where, $fields);
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

            $class = get_class($this);
            $c = new $class();
            foreach ($columns as $key => $value) {
                if (!isset($record[$key])) {
                    continue;
                } else {
                    $c->$value['raw'] = $record[$key];
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
            $return = $collection->remove($query, ['justOne' => true]);
        }

        public static function deleteAll($query = []) {
            $instance = new static();
            $query = $instance->_updateQuery($query);
            $collection = $instance->getTable();

            $return = $collection->remove($query);
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
