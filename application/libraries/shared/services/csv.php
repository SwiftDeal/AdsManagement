<?php
namespace Shared\Services;

class Csv {
	protected $_data;

	public function __construct($data) {
		$this->_data = $data;
	}

	public function write($file = "php://output") {
		$csv = $this->_convert();

		$fp = fopen($file, 'w');
		foreach ($csv as $value) {
		    fputcsv($fp, $value);
		}
		fclose($fp);
	}

	public function getObjectKeys($value) {
		$keys = [];
		if (is_a($value, 'Framework\Model')) {
			$d = $value->getAllProperties();

			foreach ($d as $k => $v) {
				$keys[] = substr($k, 1);
			}
		} else if (is_a($value, 'stdClass')) {
			foreach ($value as $k => $v) {
				$keys[] = $k;
			}
		}
		return $keys;
	}

	protected function _objToCsv($value) {
		$keys = $this->getObjectKeys($value);

		$ans = [];
		foreach ($keys as $k) {
			$current = $value->$k;
			switch (gettype($current)) {
				case 'array':
					$second = implode(",", $current);
					break;

				case 'object':
					if (is_a($current, 'DateTime')) {
						$second = $current->format('Y-m-d');
					} else {
						$second = sprintf('%s', $current);
					}
					break;
				
				default:
					$second = $current;
					break;
			}
			$ans[$k] = $second;
		}
		return $ans;
	}

	protected function _convert() {
		$arr = []; $data = $this->_data;

		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$arr[] = [$key];

				$first = array_values($value)[0];
				if (is_object($first)) {
					$arr[] = $this->getObjectKeys($first);
				}

				foreach ($value as $k => $val) {
					if (is_object($val)) {
						$arr[] = array_values($this->_objToCsv($val));
					} else {
						$arr[] = $val;
					}
				}
			} else if (is_object($value)) {
				$arr[] = [$key];

				$keys = $this->getObjectKeys($value);
				$ans = $this->_objToCsv($value);
				foreach ($keys as $k) {
					$arr[] = [$k, $ans[$k]];
				}
			} else {
				$arr[] = [$key, $value];
			}
			$arr[] = ["\n"];
		}
		return $arr;
	}
}