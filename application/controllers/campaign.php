<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use WebBot\Core\Bot as Bot;
use \Curl\Curl;

class Campaign extends Publisher {

    protected $rpm = array(
        "IN" => 140,
        "US" => 140,
        "CA" => 140,
        "AU" => 140,
        "GB" => 140,
        "NONE" => 80
    );

    /**
     * @before _secure, _layout
     */
    public function index() {
        $this->seo(array("title" => "Link Share Campaign", "description" => "All campaign sorted by newly added", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if (!$this->publish->live) {
            return;
        }
        
        $title = RequestMethods::get("title", "");
        $category = RequestMethods::get("category", "");
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 12);

        $where = array(
            "title LIKE ?" => "%{$title}%",
            "live = ?" => true
        );
        
        $items = Item::all($where, array("id", "title", "image", "url", "description"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("limit", $limit);
        $view->set("title", $title);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("items", $items);
        $view->set("category", $category);
        $view->set("domains", $this->target());
    }
    
    /**
     * @before _secure, _layout
     */
    public function create() {
        $this->seo(array("title" => "Create Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::get("link")) {
            $view->set("meta", $this->_bot(RequestMethods::get("link")));
        } else {
            $this->redirect("/campaign/manage.html");
        }

        $view->set("errors", array());
        if (RequestMethods::post("action") == "content") {
            if (RequestMethods::post("image_url")) {
                $image = $this->urls3upload(RequestMethods::post("image_url"));
            } else {
                $image = $this->s3upload("image", "images");
            }
            $ad = new Ad(array(
                "user_id" => $this->user->id,
                "url" =>  RequestMethods::post("url"),
                "target" =>  RequestMethods::post("url"),
                "title" => RequestMethods::post("title"),
                "description" => RequestMethods::post("description", ""),
                "image" => $image,
                "category" => json_encode(RequestMethods::post("category")),
                "coverage" => json_encode(RequestMethods::post("coverage", "IN")),
                "budget" => RequestMethods::post("budget"),
                "frequency" => RequestMethods::post("frequency", 2),
                "start" => RequestMethods::post("start"),
                "end" => RequestMethods::post("end"),
                "cpc" => RequestMethods::post("cpc", 200),
                "visibility" => "0",
                "live" => 0
            ));

            $mongoad = new \Models\Mongo\Ad();

            if ($ad->validate()) {
                $ad->save();
                $mongoad->duplicate($ad);
                
                $categories = json_decode($ad->category);
                foreach ($categories as $key => $value) {
                    $cat = new \Models\Mongo\AdCategory([
                        'ad_id' => $ad->id,
                        'category_id' => $value
                    ]);
                    $cat->save();
                }
                $view->set("message", "Campaign Created Successfully, will be approved within 24 hours.<a href='/campaign/manage.html'>Manage Campaigns</a>");
            }  else {
                $view->set("errors", $ad->getErrors());
            }
        }
    }

    /**
     * @before _secure, _layout
     */
    public function update($id = NULL) {
        $this->seo(array("title" => "Edit Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $item = Item::first(array("id = ?" => $id, "user_id = ?" => $this->user->id));
        if (!$item) {
            $this->redirect("/advertiser/index.html");
        }
        
        if (RequestMethods::post("action") == "update") {
            $item->title = RequestMethods::post("title");
            $item->url = RequestMethods::post("url");
            $item->description = RequestMethods::post("description");
            $item->live = 0;
            if ($item->validate()) {
                if (is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $item->image = $this->s3upload("image", "images");
                }
                $item->save();
                $view->set("message", "Campaign Updated Successfully");
            }  else {
                $view->set("errors", $item->getErrors());
            }
        }
        $view->set("item", $item);
    }

    protected function _bot($url) {
        Bot::$logging = false; // Disable logging
        $bot = new Bot(['cloud' => $url]);
        $bot->execute();
        $doc = array_shift($bot->getDocuments());
        $data = [];

        $type = $doc->getHttpResponse()->getType();
        if (preg_match("/image/i", $type)) {
            $data["image"] = $data["url"] = $url;
            $data["description"] = $data["title"] = "..";
            return $data;
        }
        try {
            $data["title"] = $doc->query("/html/head/title")->item(0)->nodeValue;
            $data["url"] = $url;

            $metas = $doc->query("/html/head/meta");
            for ($i = 0; $i < $metas->length; $i++) {
                $meta = $metas->item($i);
                
                if($meta->getAttribute('name') == 'description') {
                    $data["description"] = $meta->getAttribute('content');
                }

                if($meta->getAttribute('property') == 'og:image') {
                    $data["image"] = $meta->getAttribute('content');
                }
            }
        } catch (\Exception $e) {
            $data["url"] = $url;
            $data["image"] = $data["description"] = $data["title"] = "";
        }
        return $data;
    }
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function all() {
        $this->seo(array("title" => "Manage Campaign", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);

        if (in_array($property, array("url", "title", "category"))) {
            $where = array(
                "{$property} LIKE ?" => "%{$value}%"
            );
        } else {
            $where = array("{$property} = ?" => $value);
        }

        $contents = Item::all($where, array("id", "title", "modified", "image", "visibility", "url", "live", "user_id"), "modified", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("contents", $contents);
        $view->set("property", $property);
        $view->set("value", $value);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }

    /**
     * @before _secure, _layout
     */
    public function manage() {
        $this->seo(array("title" => "Manage Campaign", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        $title = RequestMethods::get("title", "");
        
        $where = array(
            "title LIKE ?" => "%{$title}%",
            "user_id = ?" => $this->user->id
        );
        
        $ads = Ad::all($where, array("id", "title", "created", "image", "url", "live", "visibility"), "created", "desc", $limit, $page);
        $count = Ad::count($where);

        $view->set("ads", $ads);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }
    
    /**
     * @before _secure, changeLayout, _admin
     */
    public function edit($id = NULL) {
        $this->seo(array("title" => "Edit Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $item = Item::first(array("id = ?" => $id));
        $rpm = RPM::first(array("item_id = ?" => $item->id));

        $rpms = array();
        foreach (json_decode($rpm->value, true) as $key => $value) {
            array_push($rpms, array(
                "country" => $key,
                "value" => $value
            ));
        }
        
        if (RequestMethods::post("action") == "update") {
            $item->model = RequestMethods::post("model");
            $item->url = RequestMethods::post("url");
            $item->title = RequestMethods::post("title");
            $item->visibility = RequestMethods::post("visibility");
            $item->category = implode(",", RequestMethods::post("category"));
            $item->description = RequestMethods::post("description");
            $item->live = RequestMethods::post("live", 0);
            $item->save();

            $rpm->value = json_encode(RequestMethods::post("rpm"));
            $rpm->save();

            $view->set("success", true);
            $view->set("errors", $item->getErrors());
        }
        $view->set("item", $item);
        $view->set("rpms", $rpms);
        $view->set("categories", explode(",", $item->category));
    }

    public function resize($image, $width = 600, $height = 315) {
        $path = APP_PATH . "/public/assets/uploads/images";$cdn = CLOUDFRONT;
        $image = base64_decode($image);
        if ($image) {
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);

            if ($filename && $extension) {
                $thumbnail = "{$filename}-{$width}x{$height}.{$extension}";
                if (!file_exists("{$path}/{$thumbnail}")) {
                    $imagine = new \Imagine\Gd\Imagine();
                    $size = new \Imagine\Image\Box($width, $height);
                    $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                    $imagine->open("{$path}/{$image}")->thumbnail($size, $mode)->save("{$path}/resize/{$thumbnail}");

                    /*$s3 = $this->_s3();

                    $string = file_get_contents("{$path}/resize/{$thumbnail}");
                    $result = $s3->putObject([
                        'Bucket' => 's3.vnative.com',
                        'Key' => 'images/resize/' . $thumbnail,
                        'Body' => $string
                    ]);*/
                }
                $this->redirect("{$cdn}images/resize/{$thumbnail}");
            }
        } else {
            $this->redirect("{$cdn}img/logo.png");
        }
    }
}
