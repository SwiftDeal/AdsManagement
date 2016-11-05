<?php
namespace Keyword;
use Framework\ArrayMethods;
use Curl\Curl;

class Scrape {
	protected $url;

	protected function _html() {
		$curl = new Curl();
        $curl->setHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36');
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $html = $curl->get($this->url);

        return $html;
	}

	public function __construct($url) {
		$this->url = $url;
	}

	protected function _fetch() {
		$html = $this->_html();
		$allWordsArray = str_word_count(strip_tags($html), 1);
        $allWordsArray = Helper::clean($allWordsArray);
        $allWordsArray = Helper::removeCommonWords($allWordsArray);

        return ArrayMethods::clean($allWordsArray);
	}

	public function fetch() {
		$allWordsArray = $this->_fetch();

		$wordCount = Helper::countValues($allWordsArray);
		return ArrayMethods::topValues($wordCount, count($wordCount));
	}

	public function fetchRaw() {
		$allWordsArray = $this->_fetch();
	}
}