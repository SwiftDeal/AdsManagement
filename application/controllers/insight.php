<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Shared\Services\Performance as Perf;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Insight extends Auth {
	/**
	 * @readwrite
	 * @var string
	 */
	protected $_start = null;
	
	/**
	 * @readwrite
	 * @var string
	 */
	protected $_end = null;

    /**
     * @readwrite
     * @var string
     */
    protected $_user_id = null;

	public function __construct($opts = []) {
		parent::__construct($opts);

		$start = RequestMethods::get('start', date('Y-m-d', strtotime("-1 day")));
		$end = RequestMethods::get('end', date('Y-m-d'));
        $user_id = RequestMethods::get("user_id", null);

		$this->start = $start; $this->end = $end;
        $this->user_id = $user_id;
	}

    /**
     * @protected
     */
    public function setDate() {
        $this->getActionView()->set([
            'start' => $this->start, 'end' => $this->end
        ]);
    }

	/**
     * @before _secure
     * @after setDate
     */
	public function campaign($id = null) {
		$this->seo(["title" => "Campaign Insights"]);
        $view = $this->getActionView(); $org = $this->org;
        $ad = Ad::first(['_id' => $id, 'org_id' => $org->_id]);
        if (!$ad) $this->_404();

        $data = Shared\Services\Campaign::performance($id, [
            'start' => $this->start, 'end' => $this->end, 'pid' => $this->user_id,
            'meta' => true
        ]);

        $view->set('stats', $data['stats'])
            ->set('total', $data['total']);
	}

    /**
     * @before _secure
     * @after setDate
     */
    public function organization() {
        $this->seo(["title" => "Organization Stats"]);
        $view = $this->getActionView(); $org = $this->org;
        $data = Perf::stats($org, [
            'start' => $this->start,
            'end' => $this->end,
            'meta' => true
        ]);
        
        $view->set($data);
    }

    /**
     * @before _secure
     * @after setDate
     */
    public function publishers() {
        $this->seo(["title" => "Publisher Stats"]);
        $view = $this->getActionView(); $org = $this->org;

        if ($this->user_id) {
            $publisher = User::first(['_id' => $this->user_id, 'org_id' => $org->_id, 'type' => 'publisher']);
            if (!$publisher) $this->_404();
            $in = [$publisher->_id];
        } else {
            $in = $org->users('publisher');
        }

        $pubPerf = Perf::perf($org, 'publisher', [
            'meta' => true, 'publishers' => $in,
            'start' => $this->start, 'end' => $this->end
        ]);
        $perf = []; Perf::payout($pubPerf, $perf);
        $data = ['stats' => $perf, 'total' => Perf::calTotal($perf)];
        $view->set($data);
    }

    /**
     * @before _secure
     * @after setDate
     */
    public function advertisers() {
        $this->seo(["title" => "Advertiser Stats"]);
        $view = $this->getActionView(); $org = $this->org;

        if ($this->user_id) {
            $advertiser = User::first(['_id' => $this->user_id, 'org_id' => $org->_id, 'type' => 'advertiser']);
            if (!$advertiser) $this->_404();
            $in = [$advertiser->_id];
        } else {
            $in = $org->users('advertiser');
        }

        $fields = ['clicks', 'impressions', 'conversions', 'revenue', 'created'];
        $advertPerf = Perf::perf($org, 'advertiser', [
            'advertisers' => $in,
            'start' => $this->start, 'end' => $this->end,
            'fields' => $fields
        ]);
        $perf = []; Perf::revenue($advertPerf, $perf);
        $data = ['stats' => $perf, 'total' => Perf::calTotal($perf)];
        $view->set($data);
    }

}