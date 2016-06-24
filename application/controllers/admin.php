<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Admin extends Auth {

    /**
     * @readwrite
     */
    protected $_staff;

    /**
     * @before _secure, changeLayout, _admin
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $now = strftime("%Y-%m-%d", strtotime('now'));
        $yesterday = strftime("%Y-%m-%d", strtotime('-1 day'));

        $database = Registry::get("database");
        // $payments = $database->query()->from("transactions", array("SUM(amount)" => "payment"))->where("live=?", 1)->all();
        // $instamojos = $database->query()->from("instamojos", array("SUM(amount)" => "received"))->where("live=?", 1)->all();
        $payments = $instamojos = [];
        
        $view->set("received", round($instamojos[0]["received"], 2));
        $view->set("payment", round($payments[0]["payment"], 2));
        $view->set("yesterday", $yesterday);
    }

    /**
     * Searchs for data and returns result from db
     * @param type $model the data model
     * @param type $property the property of modal
     * @param type $val the value of property
     * @before _secure, changeLayout, _admin
     */
    public function search($model = NULL, $property = NULL, $val = 0, $page = 1, $limit = 10) {
        $this->seo(array("title" => "Search", "keywords" => "admin", "description" => "admin", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $model = RequestMethods::get("model", $model);
        $property = RequestMethods::get("key", $property);
        $val = RequestMethods::get("value", $val);
        $page = RequestMethods::get("page", $page);
        $limit = RequestMethods::get("limit", $limit);
        $sign = RequestMethods::get("sign", "equal");
        $order = RequestMethods::get("order", "created");
        $sort = RequestMethods::get("sort", -1);

        $view->set("results", array());
        $view->set("fields", array());
        $view->set("model", $model);
        $view->set("models", Shared\Markup::models());
        $view->set("page", $page);
        $view->set("limit", $limit);
        $view->set("property", $property);
        $view->set("val", $val);
        $view->set("sign", $sign);
        $view->set("order", $order);
        $view->set("sort", $sort)
            ->set("count", 0);

        if ($model) {
            if ($sign == "like") {
                $where = array("{$property}" => new MongoRegex("/$val/i"));
            } else {
                $where = array("{$property} = ?" => $val);
            }

            $objects = $model::all($where, array("*"), $order, $sort, $limit, $page);
            $count = $model::count($where);

            if ($count == 0) {
                $view->set("success", "No results found");
            } else {
                $view->set("success", "Total Results : {$count}");
            }

            $m = new $model();
            $fields = $m->getColumns();

            $view->set("results", $objects)
                ->set("fields", $fields)
                ->set("count", $count);
        }
    }

    protected function downloadCSV($items) {
        $this->noview();$count = 1;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=data.csv');

        $output = fopen('php://output', 'w');

        foreach ($items as $item) {
            if ($count == 1) {
                $array_keys = array_keys($item);
                fputcsv($output, $array_keys);
                $count++;
            }

            $array_values = array_values($item);
            fputcsv($output, $array_values);
        }
    }

    /**
     * Shows any data info
     * 
     * @before _secure, changeLayout, _admin
     * @param type $model the model to which shhow info
     * @param type $id the id of object model
     */
    public function info($model = NULL, $id = NULL) {
        $this->seo(array("title" => "{$model} info", "keywords" => "admin", "description" => "admin", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $items = array();
        $values = array();

        $object = $model::first(array("id = ?" => $id));
        if (!$object) {
            $this->noview();
            echo "Not Found";
        }
        $properties = $object->getJsonData();
        foreach ($properties as $key => $property) {
            $key = substr($key, 1);
            if (strpos($key, "_id")) {
                $child = ucfirst(substr($key, 0, -3));
                $childobj = $child::first(array("id = ?" => $object->$key));
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
        $view->set("model", $model);
    }

    /**
     * Updates any data provide with model and id
     * 
     * @before _secure, changeLayout, _admin
     * @param type $model the model object to be updated
     * @param type $id the id of object
     */
    public function update($model = NULL, $id = NULL) {
        $this->seo(array("title" => "Update", "keywords" => "admin", "description" => "admin", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $object = $model::first(array("id = ?" => $id));

        $vars = $object->getColumns();
        $array = array();
        foreach ($vars as $key => $value) {
            $array[] = $key;
        }
        if (RequestMethods::post("action") == "update") {
            foreach ($array as $field) {
                $object->$field = RequestMethods::post($field, $object->$field);
            }
            $object->save();
            $view->set("success", true);
        }

        $view->set("object", $object);
        $view->set("array", $array);
        $view->set("model", $model);
        $view->set("id", $id);
        $view->set("columns", $vars);
    }

    /**
     * Edits the Value and redirects user back to Referer
     * 
     * @before _secure, changeLayout, _admin
     * @param type $model
     * @param type $id
     * @param type $property
     * @param type $value
     */
    public function edit($model, $id, $property, $value) {
        $this->JSONview();
        $view = $this->getActionView();

        $object = $model::first(array("id = ?" => $id));
        $object->$property = $value;
        $object->save();

        $view->set("object", $object);

        $this->redirect(RequestMethods::server('HTTP_REFERER', '/admin'));
    }

    /**
     * Updates any data provide with model and id
     * 
     * @before _secure, changeLayout, _admin
     * @param type $model the model object to be updated
     * @param type $id the id of object
     */
    public function delete($model = NULL, $id = NULL) {
        $view = $this->getActionView();
        $this->JSONview();
        
        $object = $model::first(array("id = ?" => $id));
        $object->delete();
        $view->set("deleted", true);
        
        $this->redirect(RequestMethods::server('HTTP_REFERER', '/admin'));
    }

    /**
     * @before _secure, changeLayout, _admin
     */
    public function dataAnalysis() {
        $this->seo(array("title" => "Data Analysis", "keywords" => "admin", "description" => "admin", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if (RequestMethods::get("action") == "dataAnalysis") {
            $startdate = RequestMethods::get("startdate");
            $enddate = RequestMethods::get("enddate");
            $model = ucfirst(RequestMethods::get("model"));

            $diff = date_diff(date_create($startdate), date_create($enddate));
            for ($i = 0; $i < $diff->format("%a"); $i++) {
                $date = date('Y-m-d', strtotime($startdate . " +{$i} day"));
                $count = $model::count(array("created" => new MongoRegex("/$date/i")));
                $obj[] = array('y' => $date, 'a' => $count);
            }
            $view->set("data", \Framework\ArrayMethods::toObject($obj));
        }
        $view->set("models", Shared\Markup::models());
    }

    /**
     * @before _secure, _admin
     */
    protected function sync($model) {
        try {
            $this->noview();
            $db = Framework\Registry::get("database");
            $db->sync(new $model);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @before _secure
     */
    public function fields($model = "user") {
        $this->noview();
        $class = ucfirst($model);
        $object = new $class;

        echo json_encode($object->columns);
    }

    public function changeLayout() {
        $session = Registry::get("session");
        $staff = $session->get("staff");
        if (!isset($staff)) {
            $this->redirect("/index.html");
        } else {
            $this->_staff = $staff;
        }

        $this->setLayout("layouts/admin");
    }

    /**
     * @protected
     */
    public function render() {
        if ($this->staff) {
            if ($this->actionView) {
                $this->actionView->set("staff", $this->staff);
            }

            if ($this->layoutView) {
                $this->layoutView->set("staff", $this->staff);
            }
        }    
        parent::render();
    }
}
