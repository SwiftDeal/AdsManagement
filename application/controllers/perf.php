<?php

use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Perf extends Auth {
	/**
     * @protected
     */
    public function _admin() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/admin");
    }

	/**
	 * @before _admin
	 */
	public function manage($uid) {
		$this->seo(array("title" => "Manage Publisher Performance"));
        $view = $this->getActionView();
        $user = \User::first(['_id' => $uid, 'org_id' => $this->org->_id], ['_id', 'name']);
        if (!$user) {
            $this->_404();
        }

        $start = RequestMethods::get("start", date("Y-m-d", strtotime('-2 day')));
        $end = RequestMethods::get("end", date("Y-m-d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);

        $performances = \Performance::all([
            'user_id' => $user->_id,
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ], [], 'created', 'desc');

        $view->set([
            'start' => $start, 'end' => $end,
            'performances' => $performances, 'pub' => $user
        ]);
	}

	/**
	 * @before _admin
	 */
	public function edit($perf_id, $uid) {
		$this->seo(array("title" => "Update Performance"));
        $view = $this->getActionView();
        $user = \User::first(['_id' => $uid, 'org_id' => $this->org->_id], ['_id', 'name']);

        $perf = \Performance::first(['_id' => $perf_id, 'user_id' => $uid]);
        if (!$user || !$perf) {
            $this->_404();
        }

        if (RequestMethods::type() === 'POST' && RequestMethods::post("perf_id") == $perf_id) {
        	$perf->clicks = (int) RequestMethods::post("clicks", $perf->clicks);
        	$perf->revenue = RequestMethods::post("revenue", $perf->revenue);
        	$perf->impressions = RequestMethods::post("impressions", 0);

        	if ($perf->clicks === 0) {
        		$perf->cpc = "0.00";
        	} else {
        		$perf->cpc = round($perf->revenue / $perf->clicks, 6);
        	}
        	$perf->save();
        	$view->set("message", "Updated Performance!!");
        }

        $view->set('perf', $perf)
        	->set('publisher', $user);
	}
}