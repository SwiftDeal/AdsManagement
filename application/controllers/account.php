<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RM;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;
use Shared\Utils as Utils;
use Shared\Services\Db;

class Account extends Admin {
    
    /**
     * @before _secure
     */
    public function manage() {
        $this->seo(array("title" => "Manage Account"));
        $view = $this->getActionView();

        $users = User::all([
            'type' => Db::convertType('admin|adm|afm', 'regex'),
            "org_id" => $this->org->_id
        ]);
        
        $view->set('users', $users);
    }

    /**
     * @before _secure
     */
    public function update($id = null) {
        $this->JSONView();
        $view = $this->getActionView();

        $usr = \User::first(['_id' => $id, 'org_id' => $this->org->_id]);
        $updateAble = ['live', 'name', 'type'];
        if (RM::type() === 'POST') {
            foreach ($updateAble as $f) {
                $usr->$f = RM::post($f);
            }
            $usr->save();

            $view->set('message', 'Accound updated!!');
        } else {
            $view->set('message', 'Invalid Request!!');
        }
    }

    /**
     * @before _secure
     */
    public function add() {
        $this->seo(array("title" => "Add Account"));
        $view = $this->getActionView();

        if (RM::type() === 'POST') {
            $role = RM::post('model');
            $usr = \User::addNew($role, $this->org, $view);
            if (!$usr) return;

            $usr->password = sha1($usr->password);
            $usr->meta = ['skype' => RM::post('skype')];
            $usr->save();

            $view->set('message', 'Member Added!!');   
        }
    }

    /**
     * @before _secure
     */
    public function edit($id) {
        $this->seo(array("title" => "Edit Account"));
        $view = $this->getActionView();

        $usr = \User::first(['_id' => $id, 'org_id' => $this->org->_id]);
        if (!$usr) {
            $this->_404();
        }

        if (RM::type() === 'POST') {
            $updateAble = ['name', 'type', 'phone', 'password'];
            foreach ($updateAble as $f) {
                $usr->$f = RM::post($f, $usr->$f);
            }
            $password = RM::post('password');
            if ($password) {
                $usr->$f = sha1($password);
            }
            $usr->save();
            $view->set('message', 'Account updated!!');
        }
        $view->set('usr', $usr);
    }

    /**
     * @before _secure
     */
    public function delete($id) {
        parent::delete($id); $view = $this->getActionView();

        $usr = \User::first(['_id' => $id, 'org_id' => $this->org->_id]);
        $allowedTypes = ['afm', 'adm'];
        if ($usr->type === 'admin') {
            $view->set('message', 'Can not remove admin!!');
        } else {
            if (in_array($usr->type, $allowedTypes)) {
                $usr->delete();
            }
            $view->set('message', 'Accout Deleted!!');
        }
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

    /**
     * @before _secure
     * @todo @Faizan_Ayubi Remove UNSAFE Actions from GET Request
     */
    public function postback() {
        $this->noview(); $session = Registry::get('session');
        $postback = PostBack::first(["id = ?" => RM::get("id")]);
        if ($postback) {
            switch (RM::get("action")) {
                case 'delete':
                    $postback->delete();
                    $session->set('$flashMessage', 'PostBack Deleted Successfully');
                    break;
                
                case 'update':
                    $property = RM::get("property");
                    $postback->$property = RM::get("value");
                    $postback->save();
                    $session->set('$flashMessage', 'PostBack Updated Successfully');
                    break;
            }
        } else {
            $session->set('$flashMessage', 'PostBack doesnot exist');
        }
        $this->redirect($_SERVER['HTTP_REFERER']);
    }
}
