<?php
namespace Shared\Services;
use Framework\ArrayMethods as AM;

class User {
	private function __construct() {}
	private function __clone() {}

	/**
	 * Function will calculate the top publisher results
	 * from the performance table
	 * @param array $query Query to filter Users
	 * @param  array   $dateQuery Result of Shared\Utils::dateQuery()
	 * @param  integer $count     Top Publisher Count
	 * @return  Array of Top Earners
	 */
	public static function topEarners($users, $dateQuery = [], $count = 10) {
		$pubClicks = []; $result = [];

		foreach ($users as $u) {
			$perf = \Performance::calculate($u, $dateQuery);

			$count = $perf['clicks'];
			if ($count === 0) {
				continue;
			}

			if (!array_key_exists($count, $pubClicks)) {
				$pubClicks[$count] = [];
			}
			$pubClicks[$count][] = AM::toObject([
				'name' => $u->name,
				'clicks' => $count
			]);
		}
		if (count($pubClicks) === 0) {
			return $result;
		}

		krsort($pubClicks);array_splice($pubClicks, $count);

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
}