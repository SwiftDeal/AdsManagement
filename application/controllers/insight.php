<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
use Shared\Services\Db as Db;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RequestMethods;

class Insight extends Admin {
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

	public function __construct($opts = []) {
		parent::__construct($opts);

		$start = RequestMethods::get('start', date('Y-m-d'));
		$end = RequestMethods::get('end', date('Y-m-d'));

		$this->start = $start; $this->end = $end;
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
		$this->seo(["title" => "ADS Effectiveness"]);
        $view = $this->getActionView(); $org = $this->org;

        // Clicks, Revenue, Rate, Profit (Revenue - Rate)
        $clickCol = Db::collection('Click');
        $limit = RequestMethods::get('limit', 30);

        $query = ['org_id' => $org->_id];
        if ($id) {
        	$query['_id'] = Db::convertType($id, 'id');
        }

        $ads = Ad::all($query, ['_id']); $in = Db::convertType(array_keys($ads), 'id');
        $records = $clickCol->aggregate([
            ['$match' => [
	                'adid' => ['$in' => $in],
	                'is_bot' => false,
	                'created' => Db::dateQuery($this->start, $this->end),
            	]
            ],
            ['$project' => ['adid' => 1, '_id' => 1, 'country' => 1]],
            ['$group' => [
            	'_id' => ['adid' => '$adid', 'country' => '$country'], 'countryCount' => ['$sum' => 1]]
            ],
            ['$group' => [
            	'_id' => '$_id.adid',
            	'countries' => [
            		'$push' => [
            			'country' => '$_id.country',
            			'count' => '$countryCount'
            		]
            	],
            	'count' => ['$sum' => '$countryCount']
            ]],
            ['$sort' => ['count' => -1]],
            ['$limit' => $limit]
        ]);

        $results = []; $commissions = [];
        foreach ($records as $r) {
        	$to = []; $obj = (object) $r;
        	$adid = Utils::getMongoID($obj->_id);
        	foreach ($obj->countries as $key => $value) {
        		$o = (object) $value;

        		$comm = Commission::campaignRate($adid, $commissions, $o->country, [
        			'type' => 'both', 'start' => $this->start, 'end' => $this->end
        		]);

        		$earning = Ad::earning($comm, $o->count);
        		ArrayMethods::add($earning, $to);
        	}
        	$results[$adid] = $to;
        }
        $view->set('endt', time());

        $view->set('ads', $results);
	}

    /**
     * @before _secure
     * @after setDate
     */
    public function organization() {
        $this->seo(["title" => "Organization Stats"]);
        $view = $this->getActionView(); $org = $this->org;
        $this->start = date('Y-m-d', strtotime('-5 day'));
        $data = Shared\Services\Performance::stats($org, ['start' => $this->start, 'end' => $this->end]);
        
        $view->set($data);
    }

    /**
     * @before _secure
     * @after setDate
     */
    public function publishers() {
        $this->seo(["title" => "Publisher Stats"]);
        $view = $this->getActionView(); $org = $this->org;
    }

    /**
     * @before _secure
     * @after setDate
     */
    public function advertisers() {
        $this->seo(["title" => "Advertiser Stats"]);
        $view = $this->getActionView(); $org = $this->org;
    }

}