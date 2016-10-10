<?php
namespace Tests\Services;

use \Curl\Curl;
use Tests\Conf as Conf;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Login {
	public static $ua = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36";

	public static function setCookie(array $cookies) {
		$ck = "";
		foreach ($cookies as $key => $value) {
			$ck .= $key . "=" . $value . ";";
		}
		return $ck;
	}

	public static function authenticate() {
		$client = new Client();
		$crawler = $client->request('GET', Conf::DOMAIN . "/");

		// select the form and fill in some values
		$form = $crawler->filter('body > div > form')->form();
		$form['email'] = Conf::EMAIL;
		$form['password'] = Conf::PASS;

		// submit that form
		$crawler = $client->submit($form);
		return $client;
	}
}