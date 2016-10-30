<?php
namespace Shared\Services;
use Framework\ArrayMethods as AM;
use Framework\RequestMethods as RequestMethods;
use Shared\Utils as Utils;

class User {
	private function __construct() {}
	private function __clone() {}

	/**
	 * Function will calculate the top publisher results
	 * from the performance table
	 * @return  Array of Top Earners
	 */
	public static function topEarners($users, $dateQuery = [], $count = 10) {
		$pubClicks = []; $result = [];

		foreach ($users as $u) {
			$perf = \Performance::calculate($u, $dateQuery);
			
			$clicks = $perf['clicks'];
			if ($clicks === 0) continue;

			if (!array_key_exists($clicks, $pubClicks)) {
				$pubClicks[$clicks] = [];
			}
			$pubClicks[$clicks][] = AM::toObject([
				'name' => $u->name,
				'clicks' => $clicks
			]);
		}
		
		if (count($pubClicks) === 0) {
			return $result;
		}

		krsort($pubClicks); array_splice($pubClicks, $count);

		$i = 0;
		foreach ($pubClicks as $key => $value) {
			foreach ($value as $u) {
				$result[] = $u;
				$i++;

				if ($i >= $count) break(2);
			}
		}
		return $result;
	}

	public static function findPerf(&$perfs, $user, $date) {
        $uid = \Shared\Utils::getMongoID($user->_id);
        if (!array_key_exists($uid, $perfs)) {
        	$p = \Performance::exists($user, $date);
        	$perfs[$uid] = $p;
        } else {
        	$p = $perfs[$uid];
        }

        return $p;
	}

	public static function find(&$search, $key, $fields = []) {
		$key = \Shared\Utils::getMongoID($key);
		if (!array_key_exists($key, $search)) {
			$usr = \User::first(['_id' => $key], $fields);
			$search[$key] = $usr;
		} else {
			$usr = $search[$key];
		}
		return $usr;
	}

	public static function trackingLinks($user, $org) {
		$default = $org->tdomains;
		$cf = \Framework\Registry::get("configuration")->parse("configuration/cf")->cloudflare;
		switch ($user->type) {
			case 'publisher':
				// check if anything set in meta
				$def = $user->meta['tdomain'] ?? $default;
				$ans = [];
				if (is_string($def)) {
					$ans[] = $def;
				} else {
					$users = \User::all(['org_id' => $org->_id, 'type' => $user->type], ['_id', 'meta']);
					$ans = $default;
					foreach ($users as $u) {
						if (isset($u->meta['tdomain'])) {
							$index = array_search($u->meta['tdomain'], $ans);
							unset($ans[$index]);
						}
					}
				}
				if (count($ans) === 0) {
					$ans[] = $cf->tracking->defaultDomain;
				}
				return $ans;
			
			default:
				return $default;
		}
	}

	public static function fields($model = 'User') {
		$cl = "\\" . $model;
		$m = new $cl;
		$columns = $m->getColumns();
		$fields = array_keys($columns);

		return $fields;
	}

	public static function display($org, $type, $id = null) {
		$fields = self::fields();
		if ($id) {
			$user = \User::first(['_id' => $id, 'org_id' => $org->_id, 'type' => $type]);
			$users = \User::objectArr($user, $fields);

			$data = ["$type" => (array) $users[0]];
		} else {
			$users = \User::all(['org_id' => $org->_id, 'type' => $type]);
			$data = ["{$type}s" => \User::objectArr($users, $fields)];
		}
		return $data;
	}

	public static function customFields($user, $org) {
		$afields = \Meta::search('customField', $org);
        if (count($afields) > 0) {
            $meta = $user->meta ?? [];
            $extraFields = [];
            foreach ($afields as $value) {
                $key = $value['name']; $type = $value['type'];
                $message = $value['label'] . " is required!!";

                switch ($type) {
                	case 'file':
                		$v = Utils::upload($key, 'images', ['extension' => 'jpe?g|gif|bmp|png|tif|pdf']);
                		if (!$v) {
                			$message = "Please Upload a valid image or pdf file";
                		}
                		break;
                	
                	case 'text':
                		$v = RequestMethods::post($key);
                		break;

                	case 'date':
                		$d = RequestMethods::post($key, date('Y-m-d'));
                		$v = Db::convertType($d, 'date');
                		break;

                	default:
                		$v = '';
                		break;
                }

                if (!$v && $value['required']) {
                	return ["message" => $message, "success" => false];
                }
                
                $extraFields[$key] = $v;
            }
            $meta['afields'] = $extraFields;
            $user->meta = $meta;
        }
        $user->save();
        return ["success" => true];
	}
}