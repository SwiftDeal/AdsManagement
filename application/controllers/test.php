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
        $date = date('Y-m-d', strtotime('-2 day'));
        $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);

        $sec = strtotime($date . ' 00:00:00');
        $clickCol = Registry::get("MongoDB")->clicks;
        $start = new \MongoDate($sec);
        $records = $clickCol->find([
            'created' => ['$gte' => $start, '$lte' => $dateQuery['end']]
        ], ['adid', 'is_bot', 'ipaddr', 'referer']);

        $classify = \Click::classify($records, 'adid');
        $referer_filtered = 0; $fraud = 0;
        $javascript_filtered = 0; $js_ref_filtered = 0;
        foreach ($classify as $key => $value) {
            $fraud += count($value);
            foreach ($value as $c) {
                if (!$c->is_bot) {
                    $javascript_filtered++;
                }
            }

            $uniqClicks = \Click::checkFraud($value);

            foreach ($uniqClicks as $c) {
                if (!$c->is_bot) {
                    $js_ref_filtered++;
                }
            }
            $referer_filtered += count($uniqClicks);
        }

        $view->set([
            'unverified' => $fraud,
            'referer_filtered' => $referer_filtered,
            'javascript_filtered' => $javascript_filtered,
            'js_ref_filtered' => $js_ref_filtered
        ]);
    }

    /**
     * @before _admin
     */
    public function publishers() {
        $this->JSONview(); $view = $this->getActionView();
        $date = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-1 day')));

        $users = \User::all(['type' => 'publisher', 'org_id' => $this->org->_id], ['_id', 'name']);
        foreach ($users as $u) {
            $in[] = $u->_id;
        }

        $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
        $query = [
            "pid" => ['$in' => $in],
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];

        $clickCol = Registry::get("MongoDB")->clicks;

        $records = $clickCol->find($query, ['adid', 'pid', 'is_bot', 'ipaddr', 'referer']);
        $classify = \Click::classify($records, 'pid');
        $pubClicks = [];
        foreach ($classify as $pid => $pClicks) {
            $orig = count($pClicks); $javascript_filtered = 0;
            foreach ($pClicks as $c) {
                if (!$c->is_bot) {
                    $javascript_filtered++;
                }
            }

            $uniqClicks = \Click::checkFraud($pClicks, $this->org);
            $referer_filtered = count($uniqClicks);
            $algo = 0.65 * $javascript_filtered + 0.35 * $referer_filtered;

            $pubClicks[$pid] = ArrayMethods::toObject([
                'name' => $users[$pid]->name,
                'unverified' => $orig,
                'referer_filtered' => $referer_filtered,
                'javascript_filtered' => $javascript_filtered
                // 'mentioned_algo' => round($algo)
            ]);
        }


        $view->set('publishers', $pubClicks);
    }
}