<?php

/**
 * Description of markup
 *
 * @author Faizan Ayubi
 */

namespace Shared {

    class Markup {

        public function __construct() {
            // do nothing
        }

        public function __clone() {
            // do nothing
        }

        public static function errors($array, $key, $separator = "<br />", $before = "<br />", $after = "") {
            if (isset($array[$key])) {
                return $before . join($separator, $array[$key]) . $after;
            }
            return "";
        }

        public static function pagination($page) {
            if (strpos(URL, "?")) {
                $request = explode("?", URL);
                if (strpos($request[1], "=")) {
                    parse_str($request[1], $params);
                }
                $params["page"] = $page;
                $finalurl = $request[0]."?".http_build_query($params);
            } else {
                $params["page"] = $page;
                $finalurl = URL."?".http_build_query($params);
            }
            return $finalurl;
        }

        public static function models() {
            $model = array();
            $path = APP_PATH . "/application/models";
            $iterator = new \DirectoryIterator($path);

            foreach ($iterator as $item) {
                if (!$item->isDot()) {
                    array_push($model, substr($item->getFilename(), 0, -4));
                }
            }
            return $model;
        }

        public function nice_number($n, $opts = []) {
            // first strip any formatting;
            $n = (0+str_replace(",", "", $n));

            // is this a number?
            if (!is_numeric($n)) return false;
            $prefix = false;
            if (isset($opts['currency'])) {
                $currency = $opts["currency"];
                if (strtolower($currency) == "usd") {
                    $n = (float) ($n / 66);
                    $prefix = '<i class="fa fa-usd"></i> ';
                } else {
                    $prefix = '<i class="fa fa-inr"></i> ';
                }
            }

            // now filter it;
            $num = false;
            if ($n > 1000000000000) $num = round(($n/1000000000000), 2).'T';
            elseif ($n > 1000000000) $num = round(($n/1000000000), 2).'B';
            elseif ($n > 1000000) $num = round(($n/1000000), 2).'M';
            elseif ($n > 1000) $num = round(($n/1000), 2).'K';
            if ($num !== false) {
                if ($prefix) $num = $prefix . $num;
                return $num;
            }

            if (is_float($n)) $n = number_format($n, 2);
            else $n = number_format($n);

            if ($prefix !== false) {
                return $prefix . $n;
            }
            return $n;
        }

        public static function get_client_ip() {
            $ipaddress = '';
            if (isset($_SERVER['HTTP_CLIENT_IP']))
                $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
            else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            else if(isset($_SERVER['HTTP_X_FORWARDED']))
                $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
            else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
                $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
            else if(isset($_SERVER['HTTP_FORWARDED']))
                $ipaddress = $_SERVER['HTTP_FORWARDED'];
            else if(isset($_SERVER['REMOTE_ADDR']))
                $ipaddress = $_SERVER['REMOTE_ADDR'];
            else
                $ipaddress = 'UNKNOWN';
            $ip = explode(",", $ipaddress);
            return $ip[0];
        }

        public static function get_server_memory_usage() {
            $free = shell_exec('free');
            $free = (string)trim($free);
            $free_arr = explode("\n", $free);
            $mem = explode(" ", $free_arr[1]);
            $mem = array_filter($mem);
            $mem = array_merge($mem);
            $memory_usage = $mem[2]/$mem[1]*100;

            return round($memory_usage);
        }



    }

}