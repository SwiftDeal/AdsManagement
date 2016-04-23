<?php
namespace Shared\Services;
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;

class GA {
	protected static $client = false;

	protected static function _data(&$analytics, $profiles, $opts) {
		$results = []; $ga = $analytics->data_ga;
		if (isset($opts['start'])) {
			$start = $opts['start'];
			$end = $opts['end'];
		} else {
			$start = date('Y-m-d', strtotime("-30 day"));
			$end = "today";
		}
		$filters = "ga:medium==Clicks99";
		if (isset($opts['filters'])) {
			$filters .= ";" . $opts['filters'];
		}
		foreach ($profiles as $p) {
			$d = $ga->get('ga:' . $p->getId(), $start, $end, "ga:pageviews, ga:sessions, ga:newUsers, ga:bounceRate", [
					"dimensions" => "ga:source, ga:medium, ga:countryIsoCode",
					"filters" => $filters,
					"max-results" => 50000
				]);

			$columns = self::_columnHeaders($d);
			$about = self::_profile($p);
			$rows = (is_array($d->getRows())) ? $d->getRows() : [];
			$results[$p->getId()] = array_merge(['about' => $about], $columns, ['totalsForAllResults' => $d->getTotalsForAllResults()], $rows);
		}
		return $results;
	}

	/**
	 * @param array $value
	 */
	public static function fields($value) {
		return [
	        'pageviews' => (int) $value[3],
	        'sessions' => (int) $value[4],
	        'newUsers' => (int) $value[5],
	        'bounceRate' => $value[6]
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

	public static function fetch(&$client, $opts = []) {
		try {
			$analytics = new \Google_Service_Analytics($client);
			
			$accounts = $analytics->management_accountSummaries;
			$items = $accounts->listManagementAccountSummaries()->getItems();

			$results = [];
			foreach ($items as $i) {
				$key = $i->getName(); // account
				$properties = $i->getWebProperties(); // properties
				foreach ($properties as $prop) {
					$d = self::_data($analytics, $prop->getProfiles(), $opts);
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
			file_put_contents(APP_PATH . '/logs/'. date('Y-m-d') . '.txt', $e->getMessage(), FILE_APPEND);
			return [];
		}
	}

	protected static function _saveWebsite($p, $user) {
		$website = \Website::first([
		    "gaid = ?" => $p['id'],
		    "url = ?" => $p['website']
		]);
		if (!$website) {
		    $website = new \Website([
		        "url" => $p['website'],
		        "gaid" => $p['id'],
		        "name" => $p['name'],
		        "live" => 1
		    ]);
		}
		$website->save();
		
		$access = \Access::first([
			"property = ?" => "website",
			"property_id = ?" => $website->id,
			"user_id = ?" => $user->id
		]);
		if (!$access) {
			$access = new \Access([
				"property" => "website",
				"property_id" => $website->id,
				"user_id" => $user->id,
				"live" => 1
			]);
			$access->save();
		}
		return $website;
	}

	protected static function _views($profiles, $user, $website, $opts = []) {
		$ga_stats = Registry::get("MongoDB")->ga_stats;
		$results = [];
		foreach ($profiles as $profile) {
		    $about = $profile['about']; $cols = $profile['columns'];
		    unset($profile['about']); unset($profile['columns']);
		    $totalsForAllResults = $profile['totalsForAllResults'];
		    unset($profile['totalsForAllResults']);

		    if (isset($opts['returnResults'])) {
		    	if (count($profile) < 1) continue;
		    	$data = [
		    		'source' => (string) $user->id,
		    		'medium' => 'Clicks99',
		    		'user_id' => (int) $user->id,
		    		'website_id' => $website->id
		    	];
		    	foreach ($totalsForAllResults as $key => $value) {
		    		$k = str_replace('ga:', '', $key);
		    		$data[$k] = $value;
		    	}
		    	$results[] = $data;
		    	continue;
		    }

		    foreach ($profile as $key => $value) {
		        $search = [
		            'source' => $value[0],
		            'medium' => $value[1],
		            'user_id' => (int) $user->id,
		            'website_id' => (int) $website->id,
		            'countryIsoCode' => $value[2],
		            'view-id' => $about['id']
		        ];
		        $data = self::fields($value);
		        $newFields = array_merge($data, $search);

		        $results[] = $newFields;
		    }
		}
		self::_filterResults($results, $opts);
		return $results;
	}

	protected static function _filterResults($results, $opts) {
		$users = [];
		foreach ($results as $r) {
			$key = $r['source']; $website = $r['website_id'];

			if (array_key_exists($key, $users) && array_key_exists($website, $users[$key])) {
				$d = array_merge($users[$key][$website], [$r]);
			} else {
				$d = [$r];
			}

			$users[$key][$website] = $d;
		}

		foreach ($users as $id => $website) { // foreach user
			foreach ($website as $_id => $data) { // foreach website
				$d = new \stdClass(); // add the data for the website
				$d->sessions = 0; $d->pageviews = 0; $d->newUsers = 0;
				foreach ($data as $r) {
					$d->views[] = $r['view-id'];
					$d->sessions += $r['sessions'];
					$d->pageviews += $r['pageviews'];
					$d->newUsers += $r['newUsers'];
				}
				$d->views = array_unique($d->views);

				// now search for the website record in mongodb
				self::_update(['user' => (int) $id, 'website' => (int) $_id], (array) $d, $opts);
			}
		}
	}

	protected static function _update($search, $data, $opts) {
		$ga_stats = Registry::get("MongoDB")->ga_stats;

		$action = isset($opts['action']) ? $opts['action'] : "update";
		$data['created'] = new \MongoDate();

		$record = $ga_stats->findOne($search);
		if ($record) {
			// check for duplicacy
			if (date('Y-m-d', $record['created']->sec) == date('Y-m-d')) return;

			$views = array_merge($data['views'], $r['views']);
			$data['views'] = array_unique($views);
			if ($action == "addition") {
				$data['sessions'] += $r['sessions'];
				$data['newUsers'] += $r['newUsers'];
				$data['pageviews'] += $r['pageviews'];
			}
			$ga->update($search, ['$set' => $data]);
		} else {
			$ga_stats->insert(array_merge($search, $data));
		}
	}

	public static function update($client, $user, $opts = []) {
		$accounts = self::fetch($client, $opts);

		$ga_stats = Registry::get("MongoDB")->ga_stats;
		$results = [];
		foreach ($accounts as $properties) {
		    foreach ($properties as $p) {
		        $website = self::_saveWebsite($p, $user);

		        $r = self::_views($p['profiles'], $user, $website, $opts);
		        $results = array_merge($results, $r);
		    }
		}
		return $results;
	}

	public static function client($token, $type = 'offline') {
		$conf = Registry::get("configuration");
        $google = $conf->parse("configuration/google")->google;

        if (!self::$client) {
            $client = new \Google_Client();
            $client->setClientId($google->client->id);
            $client->setClientSecret($google->client->secret);
            $client->setRedirectUri('http://'.RequestMethods::server("HTTP_HOST", "domain.com").'/advertiser/gaLogin');

            $client->setApplicationName("Cloudstuff");
            $client->addScope(\Google_Service_Analytics::ANALYTICS_READONLY);
            $client->setAccessType("offline");

            self::$client = $client;
        } else {
        	$client = self::$client;
        }

        if ($type == 'offline') {
        	$client->refreshToken($token);
        } else {
        	$client->setAccessToken($token);
        }
        return $client;
	}
}
