<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Shared\{Utils, Mail};
use Shared\Services\{Db, Performance as Perf};
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Test extends Auth {
    /**
     * @before _admin
     */
    public function index() {
        $this->seo(array("title" => "Manage Account")); $i = 0; $view = $this->getActionView();
        /*$ads = [];
        foreach ($ads as $a) {
            foreach ($a->category as $id) {
                $cat = Category::first(['_id' => $id]);

                $catCol = Registry::get("MongoDB")->categories;
                if (!$cat) {
                    // $catCol->insertOne([
                    //     '_id' => Db::convertType($id, 'id'),
                    //     'name' => 'hot',
                    //     'org_id' => Db::convertType($a->org_id, 'id')
                    // ]);
                } else {
                    $cat->created = date('Y-m-d');
                    $cat->name = 'hot';
                    // $cat->save();
                }
            }
            
        }*/
        $email = '';
        // $user = User::first(['email' => $email]);
        // $this->setUser($user);
    }

    /**
     * @before _admin
     */
    public function sendMail() {
        $this->noview();
        $cf = Utils::getConfig("cf", "cloudflare");

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
}
