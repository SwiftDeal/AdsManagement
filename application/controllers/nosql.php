<?php

/**
 * Controller to manage nosql db
 *
 * @author Hemant Mann
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class NoSQL extends Auth {

    /**
     * Searchs for data and returns result from db
     * @param type $model the data model
     * @param type $property the property of modal
     * @param type $val the value of property
     * @before _admin
     */
    public function search($table = NULL, $property = NULL, $val = 0, $page = 1, $limit = 10) {
        $this->seo(array("title" => "Search", "keywords" => "admin", "description" => "admin"));
        $view = $this->getActionView();
        $model = RequestMethods::get("model", $table);
        $property = RequestMethods::get("key", $property);
        $val = RequestMethods::get("value", $val);
        $page = RequestMethods::get("page", $page);
        $limit = RequestMethods::get("limit", $limit);
        $sign = RequestMethods::get("sign", "equal");

        $view->set([
            "items" => [],
            "values" => [],
            "model" => $model,
            "page" => $page,
            "limit" => $limit,
            "property" => $property,
            "val" => $val,
            "sign" => $sign,
            "count" => 0
        ]);
        if ($model) {
            $model = "Models\\". $model;
            $where = array("{$property}" => $val);

            $objects = $model::all($where, array(), "created", -1, $limit, $page);
            $count = $model::count($where);
            $i = 0;
            if ($objects) {
                foreach ($objects as $object) {
                    $properties = $object->getJsonData();
                    foreach ($properties as $key => $property) {
                        $key = substr($key, 1);
                        $items[$i][$key] = $property;
                        $values[$i][] = $key;
                    }
                    $i++;
                }
                $view->set(array(
                    "items" => $items,
                    "values" => $values[0],
                    "count" => (int) $count,
                    "success" => "Total Results : {$count}"
                ));
            } else {
                $view->set("success", "No Results Found");
            }
        }
    }

    /**
     * Shows any data info
     * 
     * @before _admin
     * @param type $model the model to which shhow info
     * @param type $id the id of object model
     */
    public function info($model = NULL, $id = NULL) {
        $this->seo(array("title" => "{$model} info", "keywords" => "admin", "description" => "admin"));
        $view = $this->getActionView();
        $items = array();
        $values = array();
        $view->set("model", $model);

        $model = "Models\\". $model;
        $object = $model::first(array("_id" => new \MongoId($id)));
        $properties = $object->getJsonData();
        foreach ($properties as $key => $property) {
            $key = substr($key, 1);
            if (strpos($key, "_id")) {
                $child = ucfirst(substr($key, 0, -3));
                $child = "Models\\" . $child;
                $childobj = $child::first(array("_id" => $object->$key));
                $childproperties = $childobj->getJsonData();
                foreach ($childproperties as $k => $prop) {
                    $k = substr($k, 1);
                    $items[$k] = $prop;
                    $values[] = $k;
                }
            } else {
                $items[$key] = $property;
                $values[] = $key;
            }
        }
        $view->set("items", $items);
        $view->set("values", $values);
    }

    /**
     * Updates any data provide with model and id
     * 
     * @before _admin
     * @param type $model the model object to be updated
     * @param type $id the id of object
     */
    public function update($table = NULL, $id = NULL) {
        $this->seo(array("title" => "Update", "keywords" => "admin", "description" => "admin"));
        $view = $this->getActionView();

        $model = "Models\\". $table;
        $object = $model::first(array("_id" => new \MongoId($id)));

        $vars = $object->columns;
        $array = array();
        foreach ($vars as $key => $value) {
            array_push($array, $key);
            $vars[$key] = htmlentities($object->$key);
        }
        if (RequestMethods::post("action") == "update") {
            foreach ($array as $field) {
                $object->$field = RequestMethods::post($field, $vars[$field]);
                $vars[$field] = htmlentities($object->$field);
            }
            $object->save();
            $view->set("success", true);
        }

        $view->set(array(
            "vars" => $vars,
            "array" => $array,
            "model" => $table,
            "_id" => $id
        ));
    }

    /**
     * Edits the Value and redirects user back to Referer
     * 
     * @before _admin
     * @param type $model
     * @param type $id
     * @param type $property
     * @param type $value
     */
    public function edit($model, $id, $property, $value) {
        $this->JSONview();
        $view = $this->getActionView();

        $model = "Models\\". $model;
        $object = $model::first(array("_id" => new \MongoId($id)));

        if (is_numeric($value)) {
            $value = (int) $value;
        } elseif (is_bool($value)) {
            $value = (bool) $value;
        }
        $object->$property = $value;
        $object->save();

        $view->set("object", $object);

        $this->redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * Deletes any model with given id
     * 
     * @before _admin
     * @param type $model the model object to be deleted
     * @param type $id the id of object to be deleted
     */
    public function delete($model = NULL, $id = NULL, $redirect = true) {
        $view = $this->getActionView();
        $this->JSONview();
        
        $model = "Models\\" . $model;
        $object = $model::first(array("_id" => new \MongoId($id)));
        if ($object->_id != $this->user->_id && strtolower($model) != 'models\user') {
            $object->delete();
            $view->set("deleted", true);   
        }
        
        if ($redirect) {
            $this->redirect($_SERVER['HTTP_REFERER']);    
        }
    }

    /**
     * @before _admin
     */
    public function dataAnalysis() {
        $this->seo(array("title" => "Data Analysis", "keywords" => "admin", "description" => "admin"));
        $view = $this->getActionView();
        if (RequestMethods::get("action") == "dataAnalysis") {
            $startdate = RequestMethods::get("startdate");
            $enddate = RequestMethods::get("enddate");
            $model = ucfirst(RequestMethods::get("model"));

            $diff = date_diff(date_create($startdate), date_create($enddate));
            for ($i = 0; $i < $diff->format("%a"); $i++) {
                $date = date('Y-m-d', strtotime($startdate . " +{$i} day"));
                $count = $model::count(array("created LIKE ?" => "%{$date}%"));
                $obj[] = array('y' => $date, 'a' => $count);
            }
            $view->set("data", \Framework\ArrayMethods::toObject($obj));
        }
    }

    protected function sync($model) {
        $this->noview();
        $db = Framework\Registry::get("database");
        try {
            $class = 'Models\\'. ucfirst($model);
            $class = new $class;
            $db->sync($class);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @before _admin
     */
    public function fields($model = "user") {
        $this->JSONview();
        $view = $this->getActionView();
        $class = 'Models\\'. ucfirst($model);
        $object = new $class;

        $view->set($object->columns);
    }

    /**
     * @before _admin
     */
    public function addNew($model) {
        $db = RequestMethods::get("model");
        if ($db) {
            $this->redirect("/admin/addNew/$db");
        }
        $this->seo(array("title" => "Add new $model"));
        $view = $this->getActionView();

        $view->set("model", $model);
        $model = 'Models\\' . $model;

        $model = new $model();
        $fields = $model->getColumns();
        if (RequestMethods::post("action") == "create") {
            foreach ($fields as $key => $value) {
                $model->$key = RequestMethods::post($key);
            }

            $model->save();
            $view->set("success", $view->get("model") . " Was added successfully!!");
        }
        $view->set("fields", $fields);
    }

    /**
     * @before _admin
     */
    public function deleteUser($user_id) {
        $session = Registry::get("session");
        $authorized = $session->get('Authenticate:$done');

        $this->noview();
        $session->set('Authenticate:$redirect', "/admin/deleteUser/$user_id");
        if (!$authorized) {
            $this->redirect("/auth/authenticate");
        }

        $user = Models\User::first(["_id" => new \MongoId($user_id)]);
        if (!$user || $user->_id == $this->user->_id) {
            $this->redirect("/404");
        }

        $models = Shared\Markup::models();
        foreach ($models as $m) {
            $m = "Models\\" . $m;

            $klass = new $m();
            if (property_exists($klass, "_user_id")) {
                // $find = $m::deleteAll(["user_id" => $user->_id]);
            }
        }
        // $user->delete();
        $this->redirect("/admin");
    }
}