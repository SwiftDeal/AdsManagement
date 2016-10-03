<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Shared\Utils as Utils;

class Account extends Admin {
    
    /**
     * @before _secure
     */
    public function manage() {
        $this->seo(array("title" => "Manage Account"));
        $view = $this->getActionView();

        $in = ["admin", "adm", "afm"];$u = [];
        $db = Registry::get("MongoDB")->users;
        $users = $db->find([
            'type' => ['$in' => $in],
            "org_id" => Utils::mongoObjectId($this->org->_id)
        ]);

        foreach ($users as $result) {
            $u[] = $result;
        }
        
        $view->set('users', $u);
    }

    /**
     * @before _secure
     */
    public function add() {
        $this->seo(array("title" => "Add Account"));
        $view = $this->getActionView();
    }

    /**
     * @before _secure
     */
    public function edit($id) {
        $this->seo(array("title" => "Edit Account"));
        $view = $this->getActionView();
    }

    /**
     * @before _secure
     */
    public function delete($id) {
        $this->noview();
    }

    /**
     * @before _secure
     */
    public function commdel() {
        $this->noview();
        $org = $this->org;
        $meta = $org->meta;
        unset($meta["commission"]);
        $org->meta = $meta;
        $org->save();
        $this->redirect("/admin/settings.html");
    }
}
