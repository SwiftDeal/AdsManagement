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
	protected static function _perf($org, $type, $opts = []) {
		$start = $opts['start'] ?? RequestMethods::get('start', date('Y-m-d', strtotime("-5 day")));
		$end = $opts['end'] ?? RequestMethods::get('end', date('Y-m-d', strtotime("-1 day")));

		switch ($type) {
			case 'publisher':
				$perfFields = ['clicks', 'impressions', 'conversions', 'revenue', 'created'];
				$meta = $opts['meta'] ?? false;
				if ($meta) { $perfFields[] = 'meta'; }
				$publishers = $org->users($type);

				$pubPerf = Perf::all([
					'user_id' => ['$in' => $publishers],
					'created' => Db::dateQuery($start, $end)
				], $perfFields, 'created', 'desc');
				$pubPerf = Perf::objectArr($pubPerf, $perfFields);
				return $pubPerf;
			
			case 'advertiser':
				$advertisers = $org->users($type);

				$advertPerf = Perf::all([
					'user_id' => ['$in' => $advertisers],
					'created' => Db::dateQuery($start, $end)
				], ['revenue', 'created'], 'created', 'desc');
				$advertPerf = Perf::objectArr($advertPerf, ['revenue', 'created']);
				return $advertPerf;
		}
		return [];
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
			$perf['meta'][$key] = ArrayMethods::topValues($arr, count($arr));
		}
	}

	/**
	 * Calculate Payout, Clicks, Impressions, Conversions from publisher performance
	 */
	protected static function _payout($pubPerf, &$perf) {
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
	protected static function _revenue($advertPerf, &$perf) {
		foreach ($advertPerf as $key => $value) {
			$date = $value->created;
			$from = ['revenue' => $value->revenue];

			if (!isset($perf[$date])) {
				$perf[$date] = [];
			}
			ArrayMethods::add($from, $perf[$date]);
		}
	}

	/**
	 * Return Date Wise Performance stats for an organization
	 * @param  object $org  \Organization
	 * @param  array  $opts Optional Array
	 * @return array       Array Containing Stats and their total
	 */
	public static function stats($org, $opts = []) {
		$pubPerf = self::_perf($org, 'publisher', $opts);
		$advertPerf = self::_perf($org, 'advertiser', $opts);

		$total = ['meta' => []]; $perf = [];
		self::_payout($pubPerf, $perf);
		self::_revenue($advertPerf, $perf);

		foreach ($perf as $key => $value) {
			ArrayMethods::add($value, $total);

			foreach ($value['meta'] as $k => $v) {
				if (!isset($total['meta'][$k])) {
					$total['meta'][$k] = [];
				}
				ArrayMethods::add($v, $total['meta'][$k]);
			}
		}

		return ['stats' => $perf, 'total' => $total];

	}
}