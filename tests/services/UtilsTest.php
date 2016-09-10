<?php
namespace Tests\Services;
use Tests\TestCase;
use Shared\Utils;
use Framework\Registry;

class UtilsTest extends TestCase {
	public function testMongoID() {
		$object = new \MongoDB\BSON\ObjectID();
		$expected = Utils::getMongoID($object);
		$known = $object->__toString();

		$this->assertEquals($known, $expected, 'ObjectID is not converted to string');
		$this->assertInstanceOf('MongoDB\BSON\ObjectID', Utils::mongoObjectId($expected), 'Failed to convert it to bson object');
	}

	/**
	 * @test
	 */
	public function imageDownload() {
		$fakeUrl = 'http://somefakeurl.com/path/to/unkown.jpg';

		$cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;
		$logo = 'http://app.' . $cf->api->domain . '/assets/img/logo.png';

		$this->assertFalse(Utils::downloadImage($fakeUrl));

		$img = Utils::downloadImage($logo);
		// Img downloaded will not return false
		$uploadsDir = APP_PATH . "/public/assets/uploads/images";
		$uploadsDirOwner = posix_getpwuid(fileowner($uploadsDir));

		$this->assertEquals('www-data', $uploadsDirOwner['name'], "Owner is not www-data Please modify the owner for successful uploading to files");
		$this->assertTrue(is_writable($uploadsDir));
		$this->assertNotEquals(false, $img, "Failed to download the image or upload it");
		$this->assertStringMatchesFormat('%s', $img);

		// If uploaded then try to remove the image
		$deleted = @unlink("{$uploadsDir}/{$img}");
		$this->assertTrue($deleted, 'Failed to delete the Image');
	}

	public function testDateQuery() {
		$start = date('Y-m-d', strtotime('-3 day')); $end = date('Y-m-d');

		$dateQuery = Utils::dateQuery($start, $end);
		$startObj = $dateQuery['start']; $endObj = $dateQuery['end'];

		$sec = $startObj->toDateTime()->getTimestamp();
		$this->assertEquals($start, date('Y-m-d', $sec), 'Start Date doesnot match');
		
		$sec = $endObj->toDateTime()->getTimestamp();
		$this->assertEquals($end, date('Y-m-d', $sec), 'End Date doesnot match');
	}
}
