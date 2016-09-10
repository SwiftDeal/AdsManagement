<?php
namespace Shared;

use WebBot\Core\Bot as Bot;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class Utils {
	public static function getMongoID($field) {
		if (is_object($field)) {
			$id = sprintf('%s', $field);	
		} else {
			$id = $field;
		}
		return $id;
	}

	public static function mongoObjectId($id) {
		if (!is_object($id) || !is_a($id, 'MongoDB\BSON\ObjectID')) {
            $id = new \MongoDB\BSON\ObjectID($id);
        }
        return $id;
	}

	public static function downloadImage($url = null) {
		if (!$url) { return false; }
		try {
			$bot = new Bot(['image' => $url], ['logging' => false]);
			$bot->execute();
			$documents = $bot->getDocuments();
			$doc = array_shift($documents);

			$contentType = $doc->type;
			preg_match('/image\/(.*)/i', $contentType, $matches);
			if (!isset($matches[1])) {
				return false;
			} else {
				$extension = $matches[1];
			}

		} catch (\Exception $e) {
			return false;
		}
		
		if (!preg_match('/^jpe?g|gif|bmp|png|tif$/', $extension)) {
			return false;
		}

		$path = APP_PATH . '/public/assets/uploads/images/';
		$img = uniqid() . ".{$extension}";

		$str = file_get_contents($url);
		if ($str === false) {
			return false;
		}
		$status = file_put_contents($path . $img, $str);
		if ($status === false) {
			return false;
		}
		return $img;
	}

	public static function particularFields($field) {
	    switch ($field) {
	        case 'name':
	            $type = 'text';
	            break;
	        
	        case 'password':
	            $type = 'password';
	            break;

	        case 'email':
	            $type = 'email';
	            break;

	        case 'phone':
	            $type = "text";
	            break;

	        default:
	            $type = 'text';
	            break;
	    }
	    return array("type" => $type);
	}

	public static function parseValidations($validations) {
	    $html = ''; $pattern = '';
	    foreach ($validations as $key => $value) {
	        preg_match("/(\w+)(\((\d+)\))?/", $value, $matches);
	        $type = isset($matches[1]) ? $matches[1] : 'none';
	        switch ($type) {
	            case 'required':
	                $html .= ' required="" ';
	                break;
	            
	            case 'max':
	                $html .= ' maxlength="' .$matches[3] . '" ';
	                break;

	            case 'min':
	                $pattern .= ' pattern="(.){' . $matches[3] . ',}" ';
	                break;
	        }
	    }
	    return array("html" => $html, "pattern" => $pattern);
	}

	public static function fetchCampaign($url) {
		$data = [];
    	
        try {
    		$bot = new Bot(['cloud' => $url], ['logging' => false]);
    	    $bot->execute();
    	    $documents = $bot->getDocuments();	// because only variables can be passed as reference
    	    $doc = array_shift($documents);

    	    $type = $doc->type;
    	    if (preg_match("/image/i", $type)) {
    	        $data["image"] = $data["url"] = $url;
    	        $data["description"] = $data["title"] = "";
    	        return $data;
    	    }
            $data["title"] = $doc->query("/html/head/title")->item(0)->nodeValue;
            $data["url"] = $url;
            $data["description"] = "";
            $data["image"] = "";

            $metas = $doc->query("/html/head/meta");
            for ($i = 0; $i < $metas->length; $i++) {
                $meta = $metas->item($i);
                
                if ($meta->getAttribute('name') == 'description') {
                    $data["description"] = $meta->getAttribute('content');
                }

                if ($meta->getAttribute('property') == 'og:image') {
                    $data["image"] = $meta->getAttribute('content');
                }
            }
        } catch (\Exception $e) {
            $data["url"] = $url;
            $data["image"] = $data["description"] = $data["title"] = "";
        }
        return $data;
	}

	public static function randomPass() {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
	}

	public static function urlRegex($url) {
		$regex = "((https?|ftp)\:\/\/)"; // SCHEME
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,4})"; // Host or IP
        $regex .= "(\:[0-9]{2,5})?"; // Port
        $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
        $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor

        $result = preg_match('/^'.$regex.'$/', $url);
        return (boolean) $result;
	}

	/**
	 * Converts dates to be passed for mongodb query
	 * @return array       mongodb start and end date
	 */
	public static function dateQuery($dateQ, $endDate = null) {
		if (!is_array($dateQ)) {
			$opts = ['start' => $dateQ, 'end' => $endDate];
		} else {
			$opts = $dateQ;
		}
		$start = strtotime("-1 day");
		$end = strtotime("+1 day");

		if (isset($opts['start'])) {
			$start = (int) strtotime($opts['start'] . ' 00:00:00');	// this returns in seconds
			$start = $start * 1000;	// we need time in milliseconds
		}

		if (isset($opts['end'])) {
			$end = (int) strtotime($opts['end'] . ' 23:59:59');
			$end = ($end * 1000) + 999;
		}

		return [
			'start' => new \MongoDB\BSON\UTCDateTime($start),
			'end' => new \MongoDB\BSON\UTCDateTime($end)
		];
	}

	public static function toArray($object) {
		$arr = [];
		$obj = (array) $object;
		foreach ($obj as $key => $value) {
			if (is_object($value) && (is_a($value, 'MongoDB\Model\BSONArray') || is_a($value, 'MongoDB\Model\BSONDocument') || is_a($value, 'stdClass'))) {
				$arr[$key] = self::toArray($value);
			} else {
				$arr[$key] = $value;
			}
		}
		return $arr;
	}

	public static function mongoRegex($val) {
		return new \MongoDB\BSON\Regex($val, 'i');
	}
}