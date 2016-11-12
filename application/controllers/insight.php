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
        if (!$id) $this->_404();

        $match = [ 'adid' => Db::convertType($id), 'is_bot' => false ];
        if ($this->user_id) {
            $match["pid"] = Db::convertType($this->user_id);
        }

        $diff = date_diff(date_create($this->start), date_create($this->end));
        $stats = []; $clickCol = Db::collection('Click');
        for ($i = 0; $i <= $diff->format("%a"); $i++) {
            $date = date('Y-m-d', strtotime($this->start . " +{$i} day"));
            $keys = ['country', 'os', 'device', 'referer'];

            $stats[$date] = [ 'clicks' => 0, 'meta' => [] ];
            $match['created'] = Db::dateQuery($date, $date);

            $records = $clickCol->aggregate([
                ['$match' => $match],
                ['$project' => ['country' => 1, 'device' => 1, 'os' => 1, 'referer' => 1]],
                ['$group' => [
                    '_id' => ['country' => '$country', 'os' => '$os', 'device' => '$device', 'referer' => '$referer'],
                    'count' => ['$sum' => 1]
                ]],
                ['$sort' => ['count' => -1]]
            ]);

            foreach ($records as $r) {
                $obj = Utils::toArray($r); $arr =& $stats[$date]['meta'];

                foreach ($keys as $k) {
                    if (!isset($arr[$k])) $arr[$k] = [];
                    $index = $r['_id'][$k];
                    ArrayMethods::counter($arr[$k], $index, $obj['count']);
                }
            }
        }

        $records = Shared\Services\Campaign::earning($stats, $id, $this->user_id);
        $total = Perf::calTotal($records);

        $view->set('ads', $records)
            ->set('total', $total)
            ->set('records', $j);
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