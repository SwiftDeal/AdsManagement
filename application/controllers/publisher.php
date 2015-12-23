<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use ClusterPoint\DB as DB;
use CouchDB\CouchDB as CouchDB;

class Publisher extends Admin {
	
	/**
     * @before _secure, changeLayout, _admin
     */
	public function settings() {
		$this->seo(array("title" => "Settings", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $login = Meta::first(array("property = ?" => "login"), array("id", "value"));
        $commision = Meta::first(array("property = ?" => "commision"));

        if (RequestMethods::post("commision")) {
        	$commision->value = RequestMethods::post("commision");
        	$commision->save();
        }

        $view->set("login", $login);
        $view->set("commision", $commision);
	}

	/**
     * @before _secure, changeLayout, _admin
     */
    public function fraud() {
        $this->seo(array("title" => "Fraud Links", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

    public function nosql() {
        $this->noview();
        $couchdb = new CouchDB('stats');
        $result = $couchdb->get_all_docs();

        // here we get the decoded json from the response
        $all_docs = $result->getBody(true);

        // then we can iterate through the returned rows and fetch each item using its id.
        foreach($all_docs->rows as $r => $row) {
            print_r($couchdb->get_item($row->id));
        }
    }
}