<?php
namespace Shared\Services;
use Framework\Registry as Registry;

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
		foreach ($profiles as $p) {
			$d = $ga->get('ga:' . $p->getId(), $start, $end, "ga:pageviews, ga:sessions, ga:percentNewSessions, ga:newUsers, ga:bounceRate, ga:avgSessionDuration, ga:pageviewsPerSession", ["dimensions" => "ga:source, ga:medium"]);

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

	public static function update($client, $user) {
		$accounts = self::fetch($client);

		$ga_stats = Registry::get("MongoDB")->ga_stats;
		foreach ($accounts as $properties) {
		    foreach ($properties as $p) {
		        $website = self::_saveWebsite($p, $user);

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
		                $data = self::fields($value, ['view-id' => $about['id']]);
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

	public static function liveStats($client, $user, $opts = []) {
		$accounts = self::fetch($client, $opts);

		$results = [];
		foreach ($accounts as $properties) {
		    foreach ($properties as $p) {
		        $website = self::_saveWebsite($p, $user);

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
		                $data = self::fields($value, ['view-id' => $about['id']]);
		                $results[] = array_merge($data, $search);
		            }
		        }
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
            $client->setRedirectUri('http://'.$_SERVER['HTTP_HOST'].'/advertiser/gaLogin');

            $client->setApplicationName("Cloudstuff");
            $client->addScope(\Google_Service_Analytics::ANALYTICS_READONLY);
            $client->setAccessType("offline");
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
