<?php
namespace Shared\Services;
use Framework\ArrayMethods as AM;

class User {
	private function __construct() {}
	private function __clone() {}

	/**
	 * Function will calculate the top publisher results
	 * from the performance table
	 * @return  Array of Top Earners
	 */
	public static function topEarners($users, $dateQuery = [], $count = 10) {
		$pubClicks = []; $result = [];

		foreach ($users as $u) {
			$perf = \Performance::calculate($u, $dateQuery);
			
			$clicks = $perf['clicks'];
			if ($clicks === 0) continue;

			if (!array_key_exists($clicks, $pubClicks)) {
				$pubClicks[$clicks] = [];
			}
			$pubClicks[$clicks][] = AM::toObject([
				'name' => $u->name,
				'clicks' => $clicks
			]);
		}
		
		if (count($pubClicks) === 0) {
			return $result;
		}

		krsort($pubClicks); array_splice($pubClicks, $count);

		$i = 0;
		foreach ($pubClicks as $key => $value) {
			foreach ($value as $u) {
				$result[] = $u;
				$i++;

				if ($i >= $count) break(2);
			}
		}
		return $result;
	}

	public static function findPerf(&$perfs, $user, $date) {
        $uid = \Shared\Utils::getMongoID($user->_id);
        if (!array_key_exists($uid, $perfs)) {
        	$p = \Performance::exists($user, $date);
        	$perfs[$uid] = $p;
        } else {
        	$p = $perfs[$uid];
        }

        return $p;
	}

	public static function find(&$search, $key, $fields = []) {
		$key = \Shared\Utils::getMongoID($key);
		if (!array_key_exists($key, $search)) {
			$usr = \User::first(['_id' => $key], $fields);
			$search[$key] = $usr;
		} else {
			$usr = $search[$key];
		}
		return $usr;
	}

	public static function trackingLinks($user, $org) {
		$default = $org->tdomains;
		switch ($user->type) {
			case 'publisher':
				// check if anything set in meta
				$def = $user->meta['tdomain'] ?? $default;
				if (is_string($def)) {
					return [$def];
				}
			
			default:
				return $default;
		}
	}

	public static function fields($model = 'User') {
		$cl = "\\" . $model;
		$m = new $cl;
		$columns = $m->getColumns();
		$fields = array_keys($columns);

		return $fields;
	}
}