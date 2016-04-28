<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use Framework\ArrayMethods as ArrayMethods;
use \Curl\Curl;

class Google extends Manage {

	/**
	 * @readwrite
	 */
	protected $_client;

	public function analytics() {
        $this->noview();
        $advertiser = Advert::all(["live = ?" => 1]);
        foreach ($advertiser as $a) {
            if (!$a->gatoken) continue;
            $client = $this->client($a->gatoken);
            $opts = [
                "start" => "2016-02-14",
                "end" => "2016-04-25",
                "case" => "countryWise"
            ];

            $accounts = $this->fetch($client, $opts);
            $results = [];
            foreach ($accounts as $properties) {
                foreach ($properties as $p) {
                    echo "<pre>", print_r($p), "</pre>";
                }
            }
            echo "<hr>";
            break;
        }
    }

   	protected function client($token, $type = 'offline') {
		$conf = Registry::get("configuration");
        $google = $conf->parse("configuration/google")->google;

        if (!$this->_client) {
            $client = new \Google_Client();
            $client->setClientId($google->client->id);
            $client->setClientSecret($google->client->secret);
            $client->setRedirectUri('http://'.RequestMethods::server("HTTP_HOST", "domain.com").'/advertiser/gaLogin');

            $client->setApplicationName("Cloudstuff");
            $client->addScope(\Google_Service_Analytics::ANALYTICS_READONLY);
            $client->setAccessType("offline");

            $this->_client = $client;
        } else {
        	$client = $this->_client;
        }

        if ($type == 'offline') {
        	$client->refreshToken($token);
        } else {
        	$client->setAccessToken($token);
        }
        return $client;
	}

	protected function fetch(&$client, $opts = []) {
		try {
			$analytics = new \Google_Service_Analytics($client);
			
			$accounts = $analytics->management_accountSummaries;
			$items = $accounts->listManagementAccountSummaries()->getItems();

			$results = [];
			foreach ($items as $i) {
				$key = $i->getName(); // account
				$properties = $i->getWebProperties(); // properties
				foreach ($properties as $prop) {
					$d = $this->_data($analytics, $prop->getProfiles(), $opts);
					$results[$key][] = array_merge(['id' => $prop->getId()], ['name' => $prop->getName()], ['website' => $prop->getWebsiteUrl()], $d);
				}
			}
			return $results;
		} catch(\Exception $e) {
			file_put_contents(APP_PATH . '/logs/'. date('Y-m-d') . '.txt', $e->getMessage(), FILE_APPEND);
			return [];
		}
	}

	protected function _data(&$analytics, $profiles, $opts) {
		$results = []; $ga = $analytics->data_ga;
		if (isset($opts['start'])) {
			$start = $opts['start'];
			$end = $opts['end'];
		} else {
			$start = date('Y-m-d', strtotime("-30 day"));
			$end = "today";
		}
		$filters = "ga:medium==Clicks99";
		foreach ($profiles as $p) {
			$d = $ga->get('ga:' . $p->getId(), $start, $end, "ga:pageviews, ga:sessions, ga:newUsers, ga:bounceRate", [
				"dimensions" => "ga:source, ga:medium",
				"filters" => $filters,
				"max-results" => 50000
			]);
			$columns = $this->_columnHeaders($d);
			$about = $this->_profile($p);
			$rows = (is_array($d->getRows())) ? $d->getRows() : [];
			$results[$p->getId()] = array_merge(['about' => $about], $columns, ['totalsForAllResults' => $d->getTotalsForAllResults()], ['rows' => $rows]);
		}
		return $results;
	}

	protected function _profile($profile) {
		return [
			'kind' => $profile->getKind(),
			'id' => $profile->getId(),
			'name' => $profile->getName(),
			'type' => $profile->getType()
		];
	}

	protected function _columnHeaders($data) {
		$headers = $data->getColumnHeaders();
		$results = [];
		foreach ($headers as $h) {
			$results[] = $h->getName();
		}
		return ['columns' => $results];
	}
}