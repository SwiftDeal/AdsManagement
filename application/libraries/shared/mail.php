<?php

namespace Shared;
use Framework\Registry as Registry;
/**
 * Static class which sends Mail using different configurations
 */
class Mail {
	/**
	 * Stores the conf
	 */
	protected static $_conf = array();
	
    protected static function _mailgun() {
    	if (isset(self::$_conf['mailgun'])) {
    		return self::$_conf['mailgun'];
    	}
        $configuration = Registry::get("configuration");
        $mailConf = $configuration->parse("configuration/mail")->mailgun;

        $mg = new \Mailgun\Mailgun($mailConf->key);
        self::$_conf['mailgun'] = $mg;
        self::$_conf['domain'] = $mailConf->domain;
        self::$_conf['team'] = $mailConf->platform;

        return self::$_conf['mailgun'];
    }
    
    protected static function _body($options) {
        $template = $options["template"];
        $view = new \Framework\View(array(
            "file" => APP_PATH . "/application/views/layouts/email/{$template}.html"
        ));
        foreach ($options as $key => $value) {
            $view->set($key, $value);
        }

        return $view->render();
    }
    
    public static function send($options) {
        $mailgun = self::_mailgun();
        $domain = self::$_conf['domain'];
        $org = $options["org"];
        $body = self::_body($options);
        $emails = isset($options["emails"]) ? $options["emails"] : array($options["user"]->email);

        $mailgun->sendMessage($domain, array(
            'from'    => "{$org->name} <info@{$org->domain}.{$domain}>",
            'to'      => $emails,
            'subject' => $options["subject"],
            'h:Reply-To'=> "<{$org->email}>",
            'html'    => $body
        ));
        self::log(implode(",", $emails));
    }

    protected static function log($message = "") {
        $logfile = APP_PATH . "/logs/" . date("Y-m-d") . ".txt";
        $new = file_exists($logfile) ? false : true;
        if ($handle = fopen($logfile, 'a')) {
            $timestamp = strftime("%Y-%m-%d %H:%M:%S", time() + 1800);
            $content = "[{$timestamp}] {$message}\n";
            fwrite($handle, $content);
            fclose($handle);
            if ($new) {
                chmod($logfile, 0755);
            }
        } else {
            //echo "Could not open log file for writing";
        }
    }
}
