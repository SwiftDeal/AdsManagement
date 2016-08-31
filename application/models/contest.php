<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
class Contest extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_org_id;

    /**
     * @column
     * @readwrite
     * @type text
     *
     * @validate required
     */
    protected $_title;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_type;

    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_start;


    /**
     * @column
     * @readwrite
     * @type datetime
     */
    protected $_end;

    /**
    * @column
    * @readwrite
    * @type array
    */
    protected $_meta = [];

    public static function updateContests($controller) {
        $org = $controller->org;
        $fields = ['title', 'start', 'end'];
        $id = RequestMethods::post('contest_id');
        if ($id) {
            $contest = self::first(['_id' => $id, 'org_id' => $org->_id]);
        } else {
            $contest = new self([ 'org_id' => $org->_id ]);
        }
        foreach ($fields as $f) {
            $contest->$f = RequestMethods::post($f);
        }

        // depending on the contest type process the fields
        $type = RequestMethods::post('type');
        switch ($type) {
            case 'clickRange': // process all the ranges
                $meta = $contest->meta;
                $condition = [];
                $rangeStart = RequestMethods::post('rangeStart', []);
                $rangeEnd = RequestMethods::post('rangeEnd', []);
                $rangePrize = RequestMethods::post('rangePrize', []);
                for ($i = 0, $total = count($rangeStart); $i < $total; ++$i) {
                    $condition[] = [
                        'start' => $rangeStart[$i],
                        'end' => $rangeEnd[$i],
                        'prize' => $rangePrize[$i]
                    ];
                }
                $meta['condition'] = $condition;
                $contest->meta = $meta;
                break;
            
            case 'topEarner':
                $meta = $contest->meta;
                $meta['condition'] = [
                    'prize' => RequestMethods::post('topEarnerPrize'),
                    'topEarnerCount' => RequestMethods::post('topEarnerCount')
                ];
                $contest->meta = $meta;
                break;

            default:
                return array('message' => 'Invalid Request!!');
        }
        $contest->type = $type;

        if ($contest->validate()) {
            $contest->save();
            return array('message' => 'Contest Added successfully!!');
        } else {
            return array('message' => 'Please fill the required fields!!');
        }
    }
}
