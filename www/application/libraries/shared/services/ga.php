<?php
namespace Shared\Services;

class GA {
	protected static function _data(&$analytics, $profiles) {
		$results = []; $ga = $analytics->data_ga;
		foreach ($profiles as $p) {
			$d = $ga->get('ga:' . $p->getId(), date('Y-m-d', strtotime("-30 day")), "today", "ga:pageviews, ga:sessions, ga:percentNewSessions, ga:newUsers, ga:bounceRate, ga:avgSessionDuration, ga:pageviewsPerSession", ["dimensions" => "ga:source, ga:medium"]);

			$columns = self::_columnHeaders($d);
			$about = self::_profile($p);
			$results[$p->getId()] = array_merge(['about' => $about], $columns, $d->getRows());
		}
		return $results;
	}

	/**
	 * @param array $value
	 */
	public static function fields($value, $user) {
		return [
	        'pageviews' => $value[2],
	        'sessions' => $value[3],
	        'percentNewSessions' => $value[4],
	        'newUsers' => $value[5],
	        'bounceRate' => $value[6],
	        'avgSessionDuration' => $value[7],
	        'pageviewsPerSession' => $value[8]
		];
	}

	protected static function _profile($profile) {
		return [
			'kind' => $profile->getKind(),
			'id' => $profile->getId(),
			'name' => $profile->getName(),
			'type' => $profile->getType()
		];
	}

	protected static function _columnHeaders($data) {
		$headers = $data->getColumnHeaders();
		$results = [];
		foreach ($headers as $h) {
			$results[] = $h->getName();
		}
		return ['columns' => $results];
	}

	public static function fetch(&$client) {
		try {
			$analytics = new \Google_Service_Analytics($client);
			
			$accounts = $analytics->management_accountSummaries;
			$items = $accounts->listManagementAccountSummaries()->getItems();

			$results = [];
			foreach ($items as $i) {
				$key = $i->getName(); // account
				$properties = $i->getWebProperties(); // properties
				foreach ($properties as $prop) {
					$d = self::_data($analytics, $prop->getProfiles());
					$results[$key][] = [
						'id' => $prop->getId(),
						'name' => $prop->getName(),
						'level' => $prop->getLevel(),
						'website' => $prop->getWebsiteUrl(),
						'profiles' => $d // views
					];
				}
			}
			return $results;
		} catch(\Exception $e) {
			return [];
		}
	}
}
