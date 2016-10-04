<?php namespace Shared\Services;
use Framework\Registry;

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
}