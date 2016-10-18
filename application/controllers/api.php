<?php

use Shared\Utils as Utils;
use Keyword\Scrape as Scraper;
use Framework\RequestMethods as RequestMethods;

class Api extends \Shared\Controller {
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