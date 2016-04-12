<?php
namespace Shared\Services;
use Framework\Registry as Registry;

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
	public static function fields($value) {
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

	public static function update($client, $user) {
		$accounts = self::fetch($client);

		$ga_stats = Registry::get("MongoDB")->ga_stats;
		foreach ($accounts as $properties) {
		    foreach ($properties as $p) {
		        $website = \Website::first([
		            "user_id = ?" => $user->id,
		            "url = ?" => $p['website']
		        ]);

		        if (!$website) {
		            $website = new \Website([
		                "user_id" => $user->id,
		                "url" => $p['website'],
		                "gaid" => $p['id'],
		                "name" => $p['name'],
		                "live" => 1
		            ]);
		        }
		        $website->save();

		        foreach ($p['profiles'] as $profile) {
		            $about = $profile['about']; $cols = $profile['columns'];
		            unset($profile['about']); unset($profile['columns']);

		            foreach ($profile as $key => $value) {
		                if ($value[1] != 'Clicks99') continue;
		                $search = [
		                    'source' => $value[0],
		                    'medium' => $value[1],
		                    'user_id' => (int) $user->id,
		                    'website_id' => (int) $website->id
		                ];
		                $data = self::fields($value);
		                $newFields = array_merge($data, $search);

		                $record = $ga_stats->findOne($search);
		                if (isset($record)) {
		                    $ga_stats->update($search, ['$set' => $data]);
		                } else {
		                    $ga_stats->insert($newFields);
		                }
		            }
		        }
		    }
		}
	}
}
