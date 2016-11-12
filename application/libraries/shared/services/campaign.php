<?php
namespace Shared\Services;
use Shared\Utils as Utils;

class Campaign {
	public static function minutely() {
		// process all the campaign which needs processing
		$today = date('Y-m-d');
		$ads = \Ad::all(['meta.processing' => true, 'created' => Db::dateQuery($today, $today)]);
		foreach ($ads as $a) {
			switch ($a->type) {
				case 'video':
					// download the video and save it
					$meta = $a->meta; unset($meta['processing']);
					$video240P = Utils::media($a->meta['videoUrl'], 'download', ['type' => 'video']);
					$video360P = Utils::media($a->meta['videoUrl'], 'download', ['type' => 'video', 'quality' => '360p']);
					
					$meta['urls'] = [$video240P, $video360P];
					$a->meta = $meta;
					$a->save();
					break;
			}
		}
	}
}
