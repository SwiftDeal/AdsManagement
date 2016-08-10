<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
class Organization extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label organizaion name
     */
    protected $_name;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label domain
     */
    protected $_domain;

    /**
     * @column
     * @readwrite
     * @type array
     * 
     * @validate required
     * @label Tracking Domains
     */
    protected $_tdomains = [];

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label default url
     */
    protected $_url = null;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * 
     * @validate required, min(3), max(255)
     * @label default support email
     */
    protected $_email = null;

    /**
     * @column
     * @readwrite
     * @type array
     * 
     * @validate required
     */
    protected $_meta = [];

    public function updateDomains() {
        $tdomains = $this->tdomains;
        $newDomains = RequestMethods::post('tdomains');

        $conf = Registry::get("configuration");
        $cf = $conf->parse("configuration/cf")->cloudflare;

        $message = null;
        if (count($newDomains) != count($tdomains)) {
            $this->tdomains = $newDomains;
            $message = 'Please update the nameservers for the newly added domains to: "' . $cf->account->ns . '"';

            $this->save();
        } else {
            $newVal = array_diff($tdomains, $newDomains);
            if (count($newVal) > 0) {
                $this->tdomains = $newDomains;

                $message = 'Please update the nameservers for the newly added domains to: "' . $cf->account->ns . '"';

                $this->save();
            }
        }
        return $message;
    }
}
