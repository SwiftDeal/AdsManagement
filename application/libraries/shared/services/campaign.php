<?php
namespace Shared\Services;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Campaign {
	public static function minutely() {
		// process all the campaign which needs processing
		$today = date('Y-m-d');
		$ads = \Ad::all(['meta.processing' => true, 'created' => Db::dateQuery($today, $today)]);
		foreach ($ads as $a) {
			$meta = $a->meta;
			switch ($a->type) {
				case 'video':
					// download the video and save it
					$arr = []; $found = false;

					foreach (['240p', '360p'] as $q) {
						$vid = Utils::media($a->meta['videoUrl'], 'download', ['type' => 'video', 'quality' => $q]);

						if (strlen($vid) >= 4) {
							$found = true;
							$arr[] = [ 'file' => $vid, 'quality' => $q ];
						}
					}
					if ($found) unset($meta['processing']);
					$meta['videos'] = $arr;
					
					$a->meta = $meta; $a->save();
					break;
			}
		}
	}

	public static function displayData($ads = []) {
	    $result = []; $ads = (array) $ads;
	    foreach ($ads as $a) {
	        $a = (object) $a;
	        $find = \Ad::first(['_id' => $a->_id], ['title', 'image', 'url']);
	        $result[] = [
	            '_id' => $a->_id,
	            'clicks' => $a->clicks,
	            'title' => $find->title,
	            'image' => $find->image,
	            'url' => $find->url
	        ];
	    }
	    return $result;
	}

	public static function earning($stats = [], $adid, $user_id = null) {
		$records = [];
		foreach ($stats as $date => $r) {
            $commissions = [];
        	foreach ($r['meta']['country'] as $country => $clicks) {
                $extra = [ 'type' => 'both', 'start' => $date, 'end' => $date ];
                if ($user_id) {
                    $extra['publisher'] = \User::first(['_id' => $user_id]);
                }

        		$comm = \Commission::campaignRate($adid, $commissions, $country, $extra);
        		$earning = \Ad::earning($comm, $clicks);
        		ArrayMethods::add($earning, $r);
        	}
            $records[$date] = $r;
        }
        return $records;
	}

	protected static function _getStats($records, &$stats, $date) {
		$keys = ['country', 'os', 'device', 'referer'];
		foreach ($records as $r) {
		    $obj = Utils::toArray($r); $arr =& $stats[$date]['meta'];

		    foreach ($keys as $k) {
		        if (!isset($arr[$k])) $arr[$k] = [];
		        $index = $r['_id'][$k] ?? null;
		        if (is_null($index)) continue;

		        if (strlen(trim($index)) === 0) {
		        	$index = "Empty";
		        }

		        ArrayMethods::counter($arr[$k], $index, $obj['count']);
		    }
		}
	}

	protected static function _perfQuery($match, $extra) {
		$meta = $extra['meta'] ?? true;
		$clickCol = Db::collection('Click');

		$group = ['country' => 1, 'device' => 1, 'os' => 1, 'referer' => 1, '_id' => 0];
		$_id = ['country' => '$country', 'os' => '$os', 'device' => '$device', 'referer' => '$referer'];
		if (!$meta) {	// if meta is not required
			$group = ['country' => 1, '_id' => 0]; $_id = ['country' => '$country'];
		}
		
		$records = $clickCol->aggregate([
		    ['$match' => $match],
		    ['$project' => $group],
		    ['$group' => [
		        '_id' => $_id,
		        'count' => ['$sum' => 1]
		    ]],
		    ['$sort' => ['count' => -1]]
		]);
		return $records;
	}

	public static function performance($id, $extra = []) {
		$stats = []; $start = $end = date('Y-m-d');

		$match = [ 'adid' => Db::convertType($id), 'is_bot' => false ];
		$user_id = $extra['pid'] ?? null;
		if ($user_id) {
            $match["pid"] = Db::convertType($user_id);
        }
        
		if (isset($extra['start']) && isset($extra['end'])) {
			$start = $extra['start']; $end = $extra['end'];
		}
		
		$diff = date_diff(date_create($start), date_create($end));

		$dateWise = $extra['meta'] ?? true;	// by default datewise query
		if ($dateWise) {
			for ($i = 0; $i <= $diff->format("%a"); $i++) {
				$date = date('Y-m-d', strtotime($start . " +{$i} day"));
				$stats[$date] = [ 'clicks' => 0, 'meta' => [] ];
				$match['created'] = Db::dateQuery($date, $date);
				
				$records = self::_perfQuery($match, $extra);
				self::_getStats($records, $stats, $date);
			}
		} else {
			$match['created'] = Db::dateQuery($start, $end);
			$stats[$start] = [ 'clicks' => 0, 'meta' => [] ];

			$records = self::_perfQuery($match, $extra);
			self::_getStats($records, $stats, $start);
		}

		$records = self::earning($stats, $id, $user_id);
        $total = Performance::calTotal($records);
        return [
        	'stats' => $records,
        	'total' => $total
        ];
	}
}
