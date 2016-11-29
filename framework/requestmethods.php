<?php

/**
 * RequestMethods class is quite simple. It has methods for returning get/post/server variables, based on a key.
 * If that key is not present, the default value will be returned. We use these methods to return our posted form data to the controller.
 *
 * @author Faizan Ayubi
 */

namespace Framework {

    class RequestMethods {

        private function __construct() {
            // do nothing
        }
        
        private function __clone() {
            // do nothing
        }

        protected static function _clean($arr = []) {
            $result = [];
            foreach ($arr as $value) {
                if (is_array($value)) {
                    $result[] = self::_clean($value);
                } else {
                    $result[] = htmlspecialchars($value);
                }
            }
            return $result;
        }
        
        public static function get($key, $default = "") {
            if (isset($_GET[$key])) {
                if (is_array($_GET[$key])) {
                    return self::_clean($_GET[$key]);
                }
                return htmlspecialchars($_GET[$key]);
            }
            return $default;
        }

        public static function post($key, $default = "") {
            if (isset($_POST[$key])) {
                if (is_array($_POST[$key])) {
                    return self::_clean($_POST[$key]);
                }
                return htmlspecialchars($_POST[$key]);
            } return $default;
        }

        public static function server($key, $default = "") {
            if (isset($_SERVER[$key])) {
                return htmlspecialchars($_SERVER[$key]);
            } return $default;
        }

        public static function type() {
            return self::server('REQUEST_METHOD');
        }

    }

}