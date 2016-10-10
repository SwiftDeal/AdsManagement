<?php
namespace Tests\Services;
use Tests\TestCase;
use Shared\Services\Db;

class RssTest extends TestCase {
	public function setUp() {
		// Connect to DB
		$this->mongoDB = Db::connect();
	}

	/**
	 * @test
	 */
	public function getPlatforms() {
		$orgs = \Organization::all([], ['_id']);
		$results = [];

		foreach ($orgs as $o) {
			$platforms = \Platform::rssFeeds($o);
			$count = count($platforms);
			if ($count === 0) {
				continue;
			}
			$results = array_merge($results, $platforms);
		}

		$this->assertNotEquals(0, count($results));
		return $results;
	}

	/**
	 * @test
	 * @depends getPlatforms
	 */
	public function getFeed(array $platforms = []) {
		$failures = 0;
		try {
			foreach ($platforms as $p) {
				$rss = $p->meta['rss'];

				if (!$rss['parsing']) {
					continue;
				}

				$result = \Shared\Rss::getFeed($rss['url'], $rss['lastCrawled']);

				if ($result['lastCrawled'] === $rss['lastCrawled']) {
					$this->assertEquals(0, count($result['urls']));
				} else {
					$this->assertNotEquals(0, count($result['urls']));
				}
			}
		} catch (\Exception $e) {
			$failures++;
		}

		$this->assertEquals(0, $failures, "Failed to crawl some Rss URL's");
	}
}