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

	// query method
}