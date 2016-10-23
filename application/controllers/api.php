<?php

use Shared\Utils as Utils;
use Keyword\Scrape as Scraper;
use Shared\Services\Db as Db;
use Shared\Services\User as Usr;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Api extends \Shared\Controller {
	const ERROR_CODES = [
		'12' => 'Unauthorized Request',
		'13' => 'Invalid API Key',
		'20' => 'Request Parameter Error',
		'30' => 'Bad Request',
		'41' => 'Request IP Not Whitelisted',
		'42' => 'Account is locked'
	];

	public function failure($code = '12') {
		$view = $this->getActionView();

		$msg = self::ERROR_CODES[$code] ?? self::ERROR_CODES['12'];
		return $view->set('error', [
			'code' => $code,
			'message' => $msg
		]);
	}

	/**
	 * @protected
	 * Check API Key
	 */
	public function _secure() {
		$headers = getallheaders();
		$key = $headers['X-Api-Key'];
		
		if (!$key) {
			$this->redirect('/api/failure/12');
		}

		$apiKey = ApiKey::first(['key' => $key]);
		if (!$apiKey) {
			$this->redirect('/api/failure/13');
		}
		
		$ip = Utils::getClientIp();

		if (!in_array($ip, $apiKey->ips)) {
			// $this->redirect('/api/failure/41');
		}

		$this->_org = Organization::first(['_id' => $apiKey->org_id]);
	}

	/**
	 * @protected
	 */
	public function _cleanUp() {
		$this->_org = null;
	}

	public function __construct($options = []) {
		parent::__construct($options);
		$this->JSONView();
	}

	public function bounceRate() {
		$this->willRenderLayoutView = $this->willRenderActionView = false;

		$output = function () {
			$name = APP_PATH . '/public/assets/img/_blue.gif';
			$fp = fopen($name, 'rb');

			header("Content-Type: image/gif");
			header("Content-Length: " . filesize($name));
			
			fpassthru($fp);
			exit;
		};
		$clickId = RequestMethods::get('ckid');
		$link = base64_decode(RequestMethods::get('link', ''));
		$ref = RequestMethods::get('ref');

		if (!$clickId || $link === false) {
			return $output();
		}

		// Find cookie from DB
		$click = Click::first(['_id' => $clickId], ['adid', 'cookie', 'pid']);
		if (!$click) return $output();

		$search = ['cookie' => $click->cookie, 'url' => $link, 'adid' => $click->adid, 'pid' => $click->pid];
		
		$pageView = PageView::first($search);
		if (!$pageView) {
			$pageView = new PageView($search);
			$pageView->view = 0;
		}
		$pageView->view++;
		$pageView->save();

		$output();
	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function affiliate($id = null) {
		// check request type
		$org = $this->_org; $view = $this->getActionView();

		if ($id) {
			$publisher = User::first(['org_id' => $org->_id, 'type' => 'publisher', '_id' => $id]);
		} else {
			$publisher = null;
		}

		if ($id && !$publisher) {
			return $this->failure('30');
		}

		$requestType = RequestMethods::type();
		switch ($requestType) {
			case 'DELETE':
				$result = $publisher->delete();
				if ($result) {
				    $view->set('message', 'Affiliate removed successfully!!');
				} else {
				    $view->set('message', 'Failed to delete. Affiliate has already given clicks!!');   
				}
				break;
			
			case 'POST':
				if (!$id) {
					$usr = User::addNew('publisher', $org, $view);
					if ($usr === false) return $view->set('success', false);

					$pass = $user->password;
					$user->password = sha1($pass);
					$user->save();

					Mail::send([
					    'user' => $user, 'org' => $org,
					    'template' => 'pubRegister', 'pass' => $pass,
					    'subject' => $org->name . ' Support'
					]);
					$view->set('message', 'Affiliate Added!!')
						->set('success', true);
				} else {
					$allowedFields = ['name', 'phone', 'country', 'currency'];
					foreach ($allowedFields as $f) {
						$publisher->$f = RequestMethods::post($f, $publisher->$f);
					}
					$publisher->save();
					$view->set('message', 'Affiliate Updated!!')
						->set('success', true);
				}
				break;

			case 'GET':
				$columns = (new User)->getColumns();
				$fields = array_keys($columns);
				if ($id) {
					$users = User::objectArr($publisher, $fields);

					$data = ['publisher' => (array) $users[0]];
					$view->set('data', $data);
				} else {
					$users = User::all(['org_id' => $org->_id, 'type' => 'publisher']);
					$data = ['publishers' => User::objectArr($users, $fields)];
					$view->set('data', $data);
				}
				break;
		}
	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function campaign($id = null) {
		$view = $this->getActionView(); $org = $this->_org;
		$active = RequestMethods::get('active', 1);

		$fields = ['_id', 'title', 'image', 'url', 'device', 'expiry', 'created'];
		$commFields = ['model', 'rate', 'revenue', 'coverage'];

		if ($id) {
			$campaign = Ad::first(['_id' => $id, 'org_id' => $org->_id], $fields);
		} else {
			$campaign = null;
		}

		if ($id && !$campaign) {
			return $this->failure('30');
		}

		$type = RequestMethods::type();
		switch ($type) {
			case 'GET':
				if (!$id) {
					// display list of campaigns
					$ads = Ad::all(['org_id' => $org->_id, 'live' => $active], $fields);
					$ads = Ad::objectArr($ads, $fields);
					
					$results = [];
					foreach ($ads as $id => $a) {
						$arr = Utils::toArray($a);
						$comms = Commission::all(['ad_id' => $a->_id], $commFields);

						$arr['commissions'] = Ad::objectArr($comms, $commFields);
						$results[$id] = (object) $arr;
					}

					$data = ['campaigns' => $results];
					$view->set('data', $data);	
				} else {
					$ads = Ad::objectArr([$campaign], $fields);
					$ad = array_shift($ads);
					$comm = Commission::all(['ad_id' => $campaign->_id], $commFields);

					$data = ['campaign' => (array) $ad, 'commissions' => Commission::objectArr($comm, $commFields)];
					$view->set('data', $data);
				}
				break;

			case 'POST':
				if ($id) { // edit a particular campaign

				} else { // create a new campaign

				}
				break;
			
			case 'DELETE':
				$message = $campaign->delete();
				$view->set('message', $message);
				break;
		}
	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function quickStats($type = 'user', $id = '') {
		$org = $this->_org; $view = $this->getActionView();
		if (!in_array($type, ['user', 'organization', 'ad'])) {
			return $this->failure('20');
		}
		$perfFields = ['clicks', 'impressions', 'conversions', 'revenue', 'created'];
		$start = RequestMethods::get("start", date('Y-m-d', strtotime("-5 day")));
		$end = RequestMethods::get("end", date('Y-m-d', strtotime('-1 day')));

		switch ($type) {
			case 'user':
				return $this->earning($id);
			
			case 'organization':
				$publishers = $org->users('publisher'); $advertisers = $org->users('advertiser');
				$pubPerf = Performance::all([
					'user_id' => ['$in' => $publishers],
					'created' => Db::dateQuery($start, $end)
				], $perfFields, 'created', 'desc');
				$pubPerf = Performance::objectArr($pubPerf, $perfFields);
				
				$advertPerf = Performance::all([
					'user_id' => ['$in' => $advertisers],
					'created' => Db::dateQuery($start, $end)
				], ['revenue', 'created'], 'created', 'desc');
				$advertPerf = Performance::objectArr($advertPerf, ['revenue', 'created']);

				$total = []; $perf = [];
				foreach ($pubPerf as $key => $value) {
					$from = (array) $value; $date = $value->created;
					unset($from['created']); unset($from['revenue']);
					$from['payout'] = $value->revenue;

					if (!isset($perf[$date])) {
						$perf[$date] = [];
					}
					ArrayMethods::add($from, $perf[$date]);
				}

				foreach ($advertPerf as $key => $value) {
					$date = $value->created;
					$from = ['revenue' => $value->revenue];

					if (!isset($perf[$date])) {
						$perf[$date] = [];
					}
					ArrayMethods::add($from, $perf[$date]);
				}
				foreach ($perf as $key => $value) {
					ArrayMethods::add($value, $total);
				}
				$view->set('data', ['stats' => $perf, 'total' => $total]);
				break;
		}

	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function earning($id = null) {
		$view = $this->getActionView();
		if (!$id) return $this->failure('20');

		$org = $this->_org; $perfFields = ['clicks', 'revenue', 'impressions', 'conversions', 'created'];
		$publisher = User::first(['_id' => $id, 'org_id' => $org->_id]);
		if (!$publisher) return $this->failure('30');

		$start = RequestMethods::get("start", date('Y-m-d', strtotime("-5 day")));
		$end = RequestMethods::get("end", date('Y-m-d', strtotime('-1 day')));

		$data = Performance::all([
			'user_id' => $publisher->_id,
			'created' => Db::dateQuery($start, $end)
		], $perfFields, 'created', 'desc');

		$total = []; $earnings = [];
		$perf = Performance::objectArr($data, $perfFields);
		foreach ($perf as $key => $p) {
			$arr = (array) $p; unset($arr['created']);
			$earnings[$p->created] = $arr;
			ArrayMethods::add($arr, $total);
		}

		$pub = User::objectArr($publisher, Usr::fields());

		$data = ['user' => $pub[0], 'earnings' => $earnings, 'total' => $total];
		$view->set('data', $data);
	}

	public function scrape() {
		$view = $this->getActionView();
		$url = RequestMethods::get("link");
		if (!$url) {
			$view->set('error', 'Invalid Request Parameters!!');
		}

		$scraper = new Scraper($url);
		$view->set('words', $scraper->fetch());
	}
}