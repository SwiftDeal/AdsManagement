<?php

namespace Framework {

    /**
     * Utility methods for working with the basic data types we ﬁnd in PHP
     */
    class ArrayMethods {

        private function __construct() {
            # code...
        }

        private function __clone() {
            //do nothing
        }

        /**
         * Useful for converting a multidimensional array into a unidimensional array.
         * 
         * @param type $array
         * @param type $return
         * @return type
         */
        public static function flatten($array, $return = array()) {
            foreach ($array as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $return = self::flatten($value, $return);
                } else {
                    $return[] = $value;
                }
            }
            return $return;
        }

        public static function first($array) {
            if (sizeof($array) == 0) {
                return null;
            }

            $keys = array_keys($array);
            return $array[$keys[0]];
        }

        public static function last($array) {
            if (sizeof($array) == 0) {
                return null;
            }

            $keys = array_keys($array);
            return $array[$keys[sizeof($keys) - 1]];
        }

        public static function toObject($array) {
            $result = new \stdClass();
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $result->{$key} = self::toObject($value);
                } else {
                    $result->{$key} = $value;
                }
            } return $result;
        }

        /**
         * Removes all values considered empty() and returns the resultant array
         * @param type $array
         * @return type the resultant array
         */
        public static function clean($array) {
            return array_filter($array, function ($item) {
                return !empty($item);
            });
        }

        /**
         * Returns an array, which contains all the items of the initial array, after they have been trimmed of all whitespace.
         * @param type $array
         * @return type array trimmed
         */
        public static function trim($array) {
            return array_map(function ($item) {
                return trim($item);
            }, $array);
        }

        /**
         * Rearranges the array keys
         */
        public static function reArray(&$array) {
            $file_ary = array();
            $file_keys = array_keys($array);
            $file_count = count($array[$file_keys[0]]);
            
            for ($i = 0; $i < $file_count; $i++) {
                foreach ($file_keys as $key) {
                    $file_ary[$i][$key] = $array[$key][$i];
                }
            }

            return $file_ary;
        }

        public static function copy(&$from, &$to) {
            foreach ($from as $key => $value) {
                $to[$key] = $value;
            }
        }

        public static function counter(&$arr, $key, $count) {
            if (!array_key_exists($key, $arr)) {
                $arr[$key] = 0;
            }
            $arr[$key] += $count;
        }

        public static function add(&$from, &$to) {
            foreach ($from as $key => $value) {
                if (!array_key_exists($key, $to)) {
                    $to[$key] = 0;
                }
                $to[$key] += $value;
            }
        }

        public static function topValues($arr, $count = 10, $order = 'desc') {
            $result = [];
            switch ($order) {
                case 'desc':
                    arsort($arr);
                    break;
                
                case 'asc':
                    asort($arr);
                    break;
            }
            
            $result = array_slice($arr, 0, $count);
            return $result;
        }

        public static function percentage($arr, $places = 2) {
            $arr = self::topValues($arr, count($arr));
            $total = array_sum($arr);
            $result = [];

            if ($total == 0) {
                return $result;
            }

            foreach ($arr as $key => $value) {
                $result[$key] = number_format(($value / $total) * 100, $places);
            }
            return $result;
        }

    }

}