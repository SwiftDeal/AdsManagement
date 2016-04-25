<?php
namespace Shared\Services;
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;

/**
 * Class to handle Google Analytics 
 */
class GAData {
	/**
	 * Parses the Google Analytics API results to filter results for each publisher
	 * and their websites (pageviews, sessions etc)
	 */
	public static function publisherWise($results) {
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

		$return = [];
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
				$d = (array) $d;

				$return[$id][$_id] = $d;
			}
		}
		return $return;
	}

	public static function publisherCountryWise($results) {

	}

	/**
	 * Formats the Google Analytics results country wise
	 */
	public static function countryWise($profile, $about, $opts) {
		$results = []; $user = $opts['user']; $website = $opts['website'];
		foreach ($profile as $key => $value) {
		    $search = [
		        'source' => $value[0],
		        'medium' => $value[1],
		        'user_id' => (int) $user->id,
		        'website_id' => (int) $website->id,
		        'countryIsoCode' => $value[2],
		        'view-id' => $about['id']
		    ];
		    $data = GA::fields($value);
		    $newFields = array_merge($data, $search);

		    $results[] = $newFields;
		}
		return $results;
	}

	/**
	 * Finds bouncerate for each publisher from the GA results
	 */
	public static function publisherBounceR($results) {
		foreach ($results as $r) {
            $key = $r['source']; unset($r['_id']);
            if (array_key_exists($key, $users)) {
                $d = array_merge($users[$key], [$r]);
            } else {
                $d = [$r];
            }
            $users[$key] = $d;
        }

        $rates = [];
        foreach ($users as $key => $value) {
            $bounceRate = 0.0; $c = 0;
            foreach ($value as $v) {
                $bounceRate += (float) $v['bounceRate'];
                $c++;
            }
            if ($c == 0) $c = 1; // Error checking
            $bouncerate = $bounceRate / $c;

            $rates[$key] = $bouncerate;
        }
        return $rates;
	}

	/**
	 * Returns the Total of GA Api
	 */
	public static function total($profile, $totalsForAllResults, $opts) {
		$user = $opts['user'];  $website = $opts['website'];
		if (count($profile) < 1) return [];
		$data = [
			'source' => "Can't calculate in overall data",
			'medium' => 'Clicks99',
			'user_id' => (int) $user->id,
			'website_id' => (int) $website->id
		];
		foreach ($totalsForAllResults as $key => $value) {
			$k = str_replace('ga:', '', $key);
			$data[$k] = $value;
		}
		return $data;
	}
}
