<?php
namespace Shared\Services;
use Framework\Registry;
use Shared\Utils as Utils;

class Db {
	public static function connect() {
		$mongoDB = Registry::get("MongoDB");
		if (!$mongoDB) {
		    require_once APP_PATH . '/application/libraries/vendor/autoload.php';
		    $configuration = Registry::get("configuration");

		    try {
		        $dbconf = $configuration->parse("configuration/database")->database->mongodb;
		        $mongo = new \MongoDB\Client("mongodb://" . $dbconf->dbuser . ":" . $dbconf->password . "@" . $dbconf->url."/" . $dbconf->dbname . "?replicaSet=" . $dbconf->replica . "&ssl=true");

		        $mongoDB = $mongo->selectDatabase($dbconf->dbname);
		    } catch (\Exception $e) {
		        throw new \Framework\Database\Exception("DB Error");   
		    }

		    Registry::set("MongoDB", $mongoDB);
		}
		return $mongoDB;
	}

	public static function convertType($value, $type = 'id') {
		switch ($type) {
			case 'id':
				return Utils::mongoObjectId($value);

			case 'regex':
				return Utils::mongoRegex($value);
			
			case 'data':
			case 'datetime':
			case 'time':
				return self::time($value);
		}
		return '';
	}

	public static function updateRaw($table, $find, $set, $opts = []) {
		$collection = Registry::get("MongoDB")->$table;
		
		$many = $opts['many'] ?? false;
		if ($many) {
			$collection->updateMany($find, $set);
		} else {
			$collection->updateOne($find, $set);
		}
	}

	public static function time($date = null) {
		if ($date) {
			$time = strtotime($date);
		} else {
			$time = strtotime('now');
		}

		return new \MongoDB\BSON\UTCDateTime($time * 1000);		
	}

	/**
	 * Checks the Default MongoDb types
	 * @param  mixed  $value 
	 * @param  string  $type  Name of the basic type
	 * @return boolean
	 */
	public static function isType($value, $type = '') {
		switch ($type) {
			case 'id':
				return is_object($value) && is_a($value, 'MongoDB\BSON\ObjectID');

			case 'regex':
				return is_object($value) && is_a($value, 'MongoDB\BSON\Regex');

			case 'document':
				return (is_object($value) && (
					is_a($value, 'MongoDB\Model\BSONArray') ||
					is_a($value, 'MongoDB\Model\BSONDocument') ||
					is_a($value, 'stdClass')
				));
			
			case 'date':
			case 'datetime':
			case 'time':
				return is_object($value) && is_a($value, 'MongoDB\BSON\UTCDateTime');

			default:
				return is_object($value) && is_a($value, 'MongoDB\BSON\ObjectID');
		}
	}

	public static function dateQuery($start = null, $end = null) {
		$changed = false;
		if ($start && $end) {
			if (is_object($start) && is_object($end) && is_a($start, 'MongoDB\BSON\UTCDateTime') && is_a($end, 'MongoDB\BSON\UTCDateTime')) {
				$dq = ['start' => $start, 'end' => $end];
				$changed = true;
			}
		}

		if (!$changed) {
			$dq = \Shared\Utils::dateQuery(['start' => $start, 'end' => $end]);	
		}

		$result = [];
		if ($start) {
			$result['$gte'] = $dq['start'];
		}

		if ($end) {
			$result['$lte'] = $dq['end'];
		}
		return $result;
	}

	public static function opts($fields = [], $order = null, $direction = null, $limit = null, $page = null) {
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

		        default:
		        	$direction = -1;
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
		return $opts;
	}

	// query method
	public static function query($model, $query, $fields = [], $order = null, $direction = null, $limit = null, $page = null) {
		$model = "\\$model";
		$m = new $model;
		$where = $m->_updateQuery($query);
		$fields = $m->_updateFields($fields);
		
		return self::_query($m, $where, $fields, $order, $direction, $limit, $page);
	}

	protected static function _query($model, $where, $fields = [], $order = null, $direction = null, $limit = null, $page = null) {
		$collection = $model->getTable();

		$opts = self::opts($fields, $order, $direction, $limit, $page);
		return $collection->find($where, $opts);
	}

	public static function count($model, $query) {
		$model = "\\$model";
		$m = new $model;
		$where = $m->_updateQuery($query);

		$collection = $m->getTable();
		return $collection->count($where);
	}
}
