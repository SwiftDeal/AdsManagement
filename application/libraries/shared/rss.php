<?php
namespace Shared;
use PicoFeed\Reader\Reader;

class Rss {
	/**
	 * Get Links from the RSS Feed URL
	 * @param  String $url  RSS Feed URL
	 * @param  String $date Date in 'Y-m-d' format
	 * @return Array       Array of URL's
	 */
	public static function getFeed($url, $date = null) {
		$reader = new Reader;

	    // Return a resource
	    $resource = $reader->download($url);

	    // Return the right parser instance according to the feed format
	    $parser = $reader->getParser(
	        $resource->getUrl(),
	        $resource->getContent(),
	        $resource->getEncoding()
	    );

	    // Return a Feed object
	    $feed = $parser->execute();

	    // Print the feed properties with the magic method __toString()
	    $urls = []; $lastCrawled = null;
	    foreach ($feed->items as $item) {
            $published = date_format($item->publishedDate, 'Y-m-d');
            if (!$lastCrawled) {
            	$lastCrawled = $published;
            }

            if ($date && $published == $date) {
                break;
            }
	    	$urls[] = urldecode($item->url);
	    }


	    return [
	    	'urls' => $urls,
	    	'lastCrawled' => $lastCrawled
	    ];
	}
}