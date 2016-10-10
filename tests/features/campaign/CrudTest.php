<?php
namespace Tests\Features\Campaign;
use Curl\Curl;
use Goutte\Client as Client;
use Tests\Services\Login as Login;
use Symfony\Component\DomCrawler\Crawler;

class CrudTest extends \Tests\TestCase {
	public function setUp() {
		$this->client = Login::authenticate();

		$cookies = $this->client->getRequest()->getCookies();

		$curl = new Curl();
		$curl->setHeader('User-Agent', Login::$ua);
		$curl->setHeader('Cookie', Login::setCookie($cookies));

		$this->curl = $curl;
	}

	/**
	 * @test
	 */
	public function manage() {
		$response = $this->curl->get(Routes::path(Routes::READ . ".json"));

		$this->assertEquals(true, is_object($response), "Response is not JSON");
		$this->assertEquals($response->count, count($response->campaigns));
	}
}