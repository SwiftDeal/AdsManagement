<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Test extends Auth {

    /**
     * @before _admin
     */
    public function perf() {
        $this->JSONview(); $view = $this->getActionView();
        $date = date('Y-m-d', strtotime('-1 day'));
        $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);

        $users = \User::all(['org_id' => '57a7631c34243d20318b456c', 'type' => 'publisher'], ['_id', 'name']);
        $clicks = 0; $in = [];
        foreach ($users as $u) {
            $in[] = $u->_id;
        }
        $table = Registry::get("MongoDB")->performances;
        $perf = $table->find([
            'user_id' => ['$in' => $in],
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ]); 
        $result = [];
        foreach ($perf as $p) {
            $p = (object) $p;
            $result[$p->clicks] = $p;
            $clicks += $p->clicks;
        }

        krsort($result);

        $clickCol = Registry::get("MongoDB")->clicks;
        $count = $clickCol->count([
            'pid' => ['$in' => $in],
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ]);
        $view->set([
            'count' => $count,
            'clicks' => $clicks,
            'p' => $result,
            'users' => $users
        ]);
    }

    /**
     * @before _admin
     */
    public function newdata() {
        $this->JSONview(); $view = $this->getActionView();
        $date = date('Y-m-d', strtotime('-1 day'));
        $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);

        $sec = strtotime($date . ' 17:27:00');
        $clickCol = Registry::get("MongoDB")->clicks;
        $start = new \MongoDate($sec);
        $records = $clickCol->find([
            'is_bot' => true,
            'created' => ['$gte' => $start, '$lte' => $dateQuery['end']]
        ], ['adid', 'ipaddr', 'referer']);

        $classify = \Click::classify($records, 'adid');
        $orig = 0; $fraud = 0;
        foreach ($classify as $key => $value) {
            $fraud += count($value);
            $uniqClicks = \Click::checkFraud($value);
            $orig += count($uniqClicks);
        }

        // These records should be valid visitor check them against 
        // fraud click algorithm
        $filtered = $clickCol->count([
            'is_bot' => false,
            'created' => ['$gte' => $start, '$lte' => $dateQuery['end']]
        ]);

        $view->set([
            'orig' => $fraud,
            'referer_filtered' => $orig,
            'javascript_filtered' => $filtered
        ]);
    }
}