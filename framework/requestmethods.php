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
        
        public static function get($key, $default = "") {
            if (!empty($_GET[$key])) {
                return htmlentities($_GET[$key]);
            }
            return $default;
        }

        public static function post($key, $default = "") {
            if (isset($_POST[$key])) {
                return htmlentities($_POST[$key]);
            } return $default;
        }

        public static function server($key, $default = "") {
            if (!empty($_SERVER[$key])) {
                return htmlentities($_SERVER[$key]);
            } return $default;
        }

        public static function type() {
            return self::server('REQUEST_METHOD');
        }

    }

}