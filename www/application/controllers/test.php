<?php
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;

class Test extends Shared\Controller {
	public function __construct($options = []) {
		parent::__construct($options);
		$conf = Framework\Registry::get("configuration");
		$google = $conf->parse("configuration/google")->google;

		$session = Registry::get("session");
		if (!Registry::get("gClient")) {
			$client = new Google_Client();
			$client->setClientId($google->client->id);
			$client->setClientSecret($google->client->secret);
			$client->setRedirectUri('http://localhost/ga/test/login');
			
			$token = $session->get('Google:$accessToken');
			if ($token) {
				$client->setAccessToken($token);
			}

			Registry::set("gClient", $client);
		}

	}

	public function index() {
		$this->noview();
		$session = Registry::get("session");

		$client = Registry::get("gClient");
		$client->setApplicationName("Cloudstuff");
		$client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);

		$token = $client->getAccessToken();
		if (!$token || $client->isAccessTokenExpired()) {
			$url = $client->createAuthUrl();
			$this->redirect($url);
		}

		try {
			$client->setAccessToken($token);
			$analytics = new Google_Service_Analytics($client);
			
			$accounts = $analytics->management_accountSummaries;
			$items = $accounts->listManagementAccountSummaries()->getItems();

			$results = [];
			foreach ($items as $i) {
				$key = $i->getName(); // account
				$properties = $i->getWebProperties(); // properties
				foreach ($properties as $prop) {
					$d = $this->_getGAdata($analytics, $prop->getProfiles());
					$results[$key][] = [
						'id' => $prop->getId(),
						'name' => $prop->getName(),
						'level' => $prop->getLevel(),
						'profiles' => $d // views
					];
				}
			}
			echo "<pre>". print_r($results, true) . "</pre>";
		} catch(\Exception $e) {
			// echo $e->getMessage();
			echo "<pre>". print_r($e, true) . "</pre>";
		}
	}

	protected function _getGAdata(&$analytics, $profiles) {
		$results = []; $ga = $analytics->data_ga;
		foreach ($profiles as $p) {
			$d = $ga->get('ga:' . $p->getId(), date('Y-m-d', strtotime("-30 day")), "today", "ga:pageviews, ga:sessions, ga:percentNewSessions, ga:newUsers, ga:bounceRate, ga:avgSessionDuration, ga:pageviewsPerSession", ["dimensions" => "ga:source, ga:medium"]);

			$columns = $this->_getColumnsHeaders($d);
			$results[$p->getId()] = array_merge($columns, $d->getRows());
		}
		return $results;
	}

	protected function _getColumnsHeaders($data) {
		$headers = $data->getColumnHeaders();
		$results = [];
		foreach ($headers as $h) {
			$results[] = $h->getName();
		}
		return [$results];
	}

	public function login() {
		$this->noview(); $session = Registry::get("session");
		$client = Registry::get("gClient");
		$code = RequestMethods::get("code");
		if (!$code) {
			$this->redirect("/ga/404");
		}

		$c = $client->authenticate($code);
		$token = $client->getAccessToken();
		if (!$token) {
			$this->redirect("/404");
		}
		$session->set('Google:$accessToken', $token);
		$this->redirect("http://localhost/ga/test");
	}
}