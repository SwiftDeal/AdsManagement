<?php
namespace Shared\Services;

use Shared\Utils as Utils;
use \Performance as Perf;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Performance {
	/**
	 * Find the Performance of Affiliates|Advertisers of an organization
	 * @param  object $org  \Organization
	 * @param  string $type Type of user
	 * @return [type]       [description]
	 */
	public static function perf($org, $type, $opts = []) {
		$start = $opts['start'] ?? RequestMethods::get('start', date('Y-m-d', strtotime("-5 day")));
		$end = $opts['end'] ?? RequestMethods::get('end', date('Y-m-d', strtotime("-1 day")));

		switch ($type) {
			case 'publisher':
				$perfFields = ['clicks', 'impressions', 'conversions', 'revenue', 'created'];
				$meta = $opts['meta'] ?? false;
				if ($meta) { $perfFields[] = 'meta'; }
				$publishers = $opts['publishers'] ?? $org->users($type);

				$pubPerf = Perf::all([
					'user_id' => ['$in' => $publishers],
					'created' => Db::dateQuery($start, $end)
				], $perfFields, 'created', 'desc');
				$pubPerf = Perf::objectArr($pubPerf, $perfFields);
				return $pubPerf;
			
			case 'advertiser':
				$advertisers = $opts['advertisers'] ?? $org->users($type);
				$fields = $opts['fields'] ?? ['revenue', 'created'];

				$advertPerf = Perf::all([
					'user_id' => ['$in' => $advertisers],
					'created' => Db::dateQuery($start, $end)
				], $fields, 'created', 'desc');
				$advertPerf = Perf::objectArr($advertPerf, $fields);
				return $advertPerf;
		}
		return [];
	}

	protected static function clean($arr) {
		$result = [];
		foreach ($arr as $key => $value) {
			$k = str_replace("-", ".", $key);
			if (is_array($value)) {
				$result[$k] = self::clean($value);
			} else {
				$result[$k] = $value;
			}
		}
		return $result;
	}

	protected static function _addMeta($meta, &$perf) {
		if (!isset($perf['meta'])) {
			$perf['meta'] = [];
		}

		foreach ($meta as $key => $value) {
			if (!isset($perf['meta'][$key])) {
				$arr = [];
			} else {
				$arr = $perf['meta'][$key];
			}

			ArrayMethods::add($value, $arr);
			$arr = ArrayMethods::topValues($arr, count($arr));
			$perf['meta'][$key] = self::clean($arr);
		}
	}

	/**
	 * Calculate Payout, Clicks, Impressions, Conversions from publisher performance
	 */
	public static function payout($pubPerf, &$perf) {
		foreach ($pubPerf as $key => $value) {
			$from = (array) $value; $date = $value->created;
			unset($from['created']); unset($from['revenue']);
			$from['payout'] = $value->revenue;

			if (isset($from['meta'])) {
				$meta = $from['meta']; unset($from['meta']);
			} else {
				$meta = [];
			}

			if (!isset($perf[$date])) {
				$perf[$date] = [];
			}
			ArrayMethods::add($from, $perf[$date]);
			self::_addMeta($meta, $perf[$date]);
		}
	}

	/**
	 * Calculate Revenue from advertiser performance
	 */
	public static function revenue($advertPerf, &$perf, $extra = []) {
		foreach ($advertPerf as $key => $value) {
			$from = (array) $value; $date = $value->created;
			unset($from['created']);

			if (!isset($perf[$date])) {
				$perf[$date] = [];
			}
			ArrayMethods::add($from, $perf[$date]);
		}
	}

	public static function calTotal($perf) {
		$total = ['meta' => []];
		foreach ($perf as $key => $value) {
			ArrayMethods::add($value, $total);

			foreach ($value['meta'] as $k => $v) {
				if (!isset($total['meta'][$k])) {
					$total['meta'][$k] = [];
				}
				ArrayMethods::add($v, $total['meta'][$k]);
			}
		}

		return $total;
	}

	/**
	 * Return Date Wise Performance stats for an organization
	 * @param  object $org  \Organization
	 * @param  array  $opts Optional Array
	 * @return array       Array Containing Stats and their total
	 */
	public static function stats($org, $opts = []) {
		$pubPerf = self::perf($org, 'publisher', $opts);
		$advertPerf = self::perf($org, 'advertiser', $opts);

		$perf = [];
		self::payout($pubPerf, $perf);
		self::revenue($advertPerf, $perf);

		return ['stats' => $perf, 'total' => self::calTotal($perf)];
	}
}