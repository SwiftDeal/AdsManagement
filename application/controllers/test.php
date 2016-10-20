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
use Shared\Services\Db;
use Framework\ArrayMethods as ArrayMethods;

class Test extends Auth {
    /**
     * @before _admin
     */
    public function sendMail() {
        $this->noview();
        $cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;

        try {
            \Shared\Services\Smtp::sendMail($this->org, [
                'template' => 'testmail',
                'user' => $this->user,
                'to' => [$cf->api->email],  // this argument expects array value
                'subject' => "Testing Mail using SMTP"
            ]);
            var_dump('Mail sent');
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }

    /**
     * @before _admin
     */
    public function publishers() {
        $this->JSONview(); $view = $this->getActionView();
        $date = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-1 day')));

        $users = \User::all(['type' => 'publisher', 'org_id' => $this->org->_id], ['_id', 'name']);
        $in = Utils::mongoObjectId(array_keys($users));

        $query = [
            "pid" => ['$in' => $in],
            "created" => Db::dateQuery($date, $date)
        ];

        $records = Db::query('Click', $query, ['adid', 'pid', 'is_bot', 'ipaddr', 'referer']);

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

            $pubClicks[$pid] = ArrayMethods::toObject([
                'name' => $users[$pid]->name,
                'unverified' => $orig,
                'referer_filtered' => $referer_filtered,
                'javascript_filtered' => $javascript_filtered
            ]);
        }

        $view->set('publishers', $pubClicks);
    }
}