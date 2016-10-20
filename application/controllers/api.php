<?php

use Shared\Utils as Utils;
use Keyword\Scrape as Scraper;
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
			$this->redirect('/api/failure/41');
		}

		$this->_org = Organization::first(['_id' => $apiKey->org_id]);
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
	 */
	public function affiliate($id = null) {
		// check request type
		$org = $this->_org; $view = $this->getActionView();
		$requestType = RequestMethods::type();

		if ($id) {
			$publisher = User::first(['org_id' => $org->_id, 'type' => 'publisher', '_id' => $id]);	
		} else {
			$publisher = null;
		}

		if ($id && !$publisher) {
			$this->redirect('/api/failure/30');
		}

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
				$users = User::all(['org_id' => $org->_id]);
				$view->set('publishers', $users);
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
}