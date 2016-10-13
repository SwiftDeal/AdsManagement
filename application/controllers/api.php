<?php

use Framework\RequestMethods as RequestMethods;
use Shared\Utils as Utils;

class Api extends Auth {
	public function __construct($options = []) {
		parent::__construct($options);
		$this->JSONView();
	}

	public function bounceRate() {
		$this->willRenderLayoutView = $this->willRenderActionView = false;

		$output = function () {
			$file = APP_PATH . '/public/assets/img/_blue.gif';
			echo file_get_contents($file);
		};
		$clickId = RequestMethods::get('ckid');
		$link = base64_decode(RequestMethods::get('link', ''));
		$ref = RequestMethods::get('ref');

		if (!$clickId || $link === false) {
			return $output();
		}

		// Find cookie from DB
		$click = Click::first(['_id' => $clickId]);
		if (!$click) return $output();
		
		$pageView = PageView::first(['cookie' => $click->cookie, 'url' => $link]);
		if (!$pageView) {
			$pageView = new PageView([
				'cookie' => $click->cookie,
				'url' => $link,
				'view' => 0
			]);
		}
		$pageView->view++;
		$pageView->save();

		$output();
	}
}