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
     * @length 6,2
     *
     * @label revenue percent
     * @validate required
     */
    protected $_prize;

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
    protected $_description;

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
        $fields = ['title', 'start', 'end', 'description'];
        $id = RequestMethods::post('contest_id');
        if ($id) {
            $contest = self::first(['_id' => $id, 'org_id' => $org->_id]);
        } else {
            $contest = new self([
                'org_id' => $org->_id,
                'prize' => round($prize, 6)
            ]);
        }
        $prize = $controller->currency(RequestMethods::post('prize'));
        foreach ($fields as $f) {
            $contest->$f = RequestMethods::post($f);
        }
        $contest->prize = round($prize, 6);

        if ($contest->validate()) {
            $contest->save();
            return array('message' => 'Contest Added successfully!!');
        } else {
            return array('message' => 'Please fill the required fields!!');
        }
    }
}
