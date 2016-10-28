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
				$this->_deleteUser($publisher, 'Affiliate');
				break;
			
			case 'POST':
				if (!$id) {
					$this->_registerUser('publisher', ['template' => 'pubRegister']);
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
				$data = Usr::display($org, 'publisher', $id);
				$view->set('data', $data);
				break;
		}
	}

	protected function _deleteUser($user, $type) {
		$result = $user->delete(); $view = $this->getActionView();
		if ($result) {
		    $view->set('message', "$type removed successfully!!");
		} else {
		    $view->set('message', "Failed to delete $type from the database!!");   
		}
	}

	protected function _registerUser($type, $opts = []) {
		$view = $this->getActionView(); $org = $this->_org;

		$usr = User::addNew($type, $org, $view);
		if ($usr === false) return $view->set('success', false);

		$pass = $user->password;
		$user->password = sha1($pass);
		$user->save();

		$params = array_merge($opts, [
		    'user' => $user, 'org' => $org,
		    'pass' => $pass,
		    'subject' => $org->name . ' Support'
		]);
		Mail::send($params);
		$view->set('message', ucfirst($type) . ' Added!!')
			->set('success', true);
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
					$ads = Ad::objectArr($campaign, $fields);
					$campaign = array_shift($ads);
					$comm = Commission::all(['ad_id' => $campaign->_id], $commFields);

					$data = ['campaign' => $campaign, 'commissions' => Commission::objectArr($comm, $commFields)];
					$view->set('data', $data);
				}
				break;

			case 'POST':
				if ($id) { // edit a particular campaign

				} else { // create a new campaign
					$fields = ['title', 'description', 'url', 'expiry'];
					$img = RequestMethods::post('image');	// contains image url
					$campaign = new Ad([
						'org_id' => $org->_id,
						'type' => 'article',
						'image' => Utils::image($img, 'download')
					]);
					foreach ($fields as $f) {
						$campaign->$f = RequestMethods::post($f);
					}

					$advertisers = $org->users('advertiser', false);
					$category = RequestMethods::post('category');
					$uid = RequestMethods::post("user_id");
					if (count($category) === 0 || count($advertisers) === 0 || !in_array($uid, $advertisers)) {
						return $this->failure('30');
					}

					$devices = RequestMethods::post('devices'); $fixedDevices = Shared\Markup::devices();
					$fixedDevices = array_keys($fixedDevices);
					if (!ArrayMethods::inArray($fixedDevices, $devices)) {
						return $view->set('message', 'Invalid Devices!!');
					}

					$categories = Category::all(['org_id' => $org->_id], ['_id']);
					$categories = array_keys($categories);
					if (!ArrayMethods::inArray($categories, $category)) {
						return $view->set('message', 'Invalid Category!!');
					}

					$visibility = RequestMethods::post('visibility', 'public');
					if ($visibility === 'private') {
						$campaign->getMeta()['private'] = true;
					}

					if ($campaign->validate()) {
						// $campaign->save();
						
					} else {
						$data = ['errors' => $campaign->errors];
						$view->set('data', $data);
					}
				}
				break;
			
			case 'DELETE':
				$message = $campaign->delete();
				$view->set($message);
				break;
		}
	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function countries() {
		$view = $this->getActionView();
		$view->set('countries', Shared\Markup::countries());
	}

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function categories($id = null) {
		$view = $this->getActionView(); $fields = ['_id', 'name'];
		$type = RequestMethods::type(); $org = $this->_org;
		$categories = Category::all(['org_id' => $org->_id], $fields);
		
		switch ($type) {
			case 'GET':
				$data = ['categories' => Category::objectArr($categories, $fields)];
				$view->set('data', $data);
				break;
			
			case 'POST':
				$updated = Category::addNew($categories, $org);
				$data = ['categories' => Category::objectArr($updated, $fields)];
				$view->set('data', $data);
				break;

			case 'DELETE':
				$cat = Category::first(['_id' => $id, 'org_id' => $org->_id]);
				if (!$id || !$cat) {
					return $this->failure('30');
				}
				if (!$cat->inUse()) {
					$cat->delete();
					$view->set('message', 'Category deleted!!');
				} else {
					$view->set('message', 'Failed to delete category because it is in use');
				}
				unset($categories[$id]);
				$data = ['categories' => Category::objectArr($categories, $fields)];
				$view->set('data', $data);
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
				$data = Shared\Services\Performance::stats($org, ['start' => $start, 'end' => $end]);
				$view->set('data', $data);
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

	/**
	 * @before _secure
	 * @after _cleanUp
	 */
	public function advertiser($id = null) {
		$view = $this->getActionView(); $org = $this->_org;

		if ($id) {
			$advertiser = User::first(['org_id' => $org->_id, 'type' => 'advertiser', '_id' => $id]);
		} else {
			$advertiser = null;
		}

		if ($id && !$advertiser) {
			return $this->failure('30');
		}

		$type = RequestMethods::type();
		switch ($type) {
			case 'GET':
				$data = Usr::display($org, 'advertiser', $id);
				$view->set('data', $data);
				break;
			
			case 'POST':
				if ($id) { // edit an advertiser
					$allowedFields = ['name', 'phone', 'country', 'currency'];
					foreach ($allowedFields as $f) {
						$advertiser->$f = RequestMethods::post($f, $publisher->$f);
					}
					$advertiser->save();
					$view->set('message', 'Advertiser Updated!!')
						->set('success', true);
				} else { // create new
					$this->_registerUser('advertiser', ['template' => 'advertReg']);
				}
				break;

			case 'DELETE':
				$this->_deleteUser($advertiser, 'Advertiser');
				break;
		}

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

	protected function _ga($ad, $ckid, $client, $link) {
		$params = array(
			'v' => 1,
			'tid' => MGAID,
			'ds' => $ad->user_id,
			'cid' => $ckid,
			'uip' => $client->ip,
			'ua' => $client->ua,
			'dr' => $client->referer,
			'ci' => $ad->_id,
			'cn' => $ad->title,
			'cs' => $link->user_id,
			'cm' => 'click',
			'cc' => $ad->title,
			't' => 'pageview',
			'dl' => URL,
			'dh' => $_SERVER['HTTP_HOST'],
			'dp' => $_SERVER['REQUEST_URI'],
			'dt' => $ad->title
		);
		
		$curl = curl_init();
		$gaurl = 'https://www.google-analytics.com/collect?'.http_build_query($params);
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $gaurl,
		    CURLOPT_USERAGENT => $client->ua,
		));
		$resp = curl_exec($curl);
		curl_close($curl);
	}
}