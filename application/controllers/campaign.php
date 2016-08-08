<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use WebBot\Core\Bot as Bot;
use \Curl\Curl;
use Shared\Utils as Utils;

class Campaign extends Admin {
    
    public function info($id) {
        $type = ($this->user) && ($this->user->type === "publisher" || $this->user->type === "advertiser");
        if (!$type) {
            $this->redirect("/404");
        } else {
            $this->setLayout("layouts/".$this->user->type);
        }
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $ad = \Ad::first(["_id = ?" => $id]);
        $this->seo(array("title" => $ad->title));
        $view = $this->getActionView();

        $commission = Commission::first(["ad_id = ?" => $id]);

        $view->set("ad", $ad);
        $view->set("c", $commission);
        $view->set("start", $start);
        $view->set("end", $end);
    }

    /**
     * @before _secure
     */
    public function create() {
    	$this->seo(['title' => 'Campaign Create', 'description' => 'Create a new campaign']);
    	$view = $this->getActionView(); $session = Registry::get('session');
        $advertisers = \User::all(["org_id = ?" => $this->org->_id, 'type = ?' => 'advertiser'], ['_id', 'name']);
        if (count($advertisers) === 0) {
            $session->set('$flashMessage', 'Please Add an Advertiser!!');
            $this->redirect('/advertiser/add.html');
        }
        $view->set('advertiser', $advertisers);

        $categories = \Category::all(['org_id' => $this->org->_id], ['name', '_id']);
        if (count($categories) === 0) {
            $session->set('$flashMessage', 'Please Set Categories!!');
            $this->redirect('/admin/settings.html');   
        }
        $view->set('categories', $categories);

    	$link = RequestMethods::get("link");
    	if (!$link) {
            $session->set('$flashMessage', 'Please give any link!!');
            $this->redirect('/campaign/manage.html');
        }
    	if ($session->get('$lastFetchedLink') !== $link) {
    		$session->set('$lastFetchedLink', $link);
    		$meta = Shared\Utils::fetchCampaign($link);

    		$session->set('Campaign\Create:$meta', $meta);
    	} else {
    		$meta = $session->get('Campaign\Create:$meta');
    	}
    	$view->set("meta", $meta)
    		->set("errors", []);

    	if (RequestMethods::type() == 'POST') {
            $img = null;
            // give preference to uploaded image
            $img = $this->_upload('image', 'images', ['extension' => 'jpe?g|gif|bmp|png|tif']);
    		if (!$img) {
                $img_url = RequestMethods::post("image_url");
    			$img = Shared\Utils::downloadImage($img_url);
    		}

    		if (!$img) {
    			return $view->set('message', 'Failed to upload the image');
    		}
    		$campaign = new \Ad([
                'user_id' => RequestMethods::post('advert_id'),
    			'title' => RequestMethods::post('title'),
    			'description' => RequestMethods::post('description'),
                'org_id' => $this->org->_id,
    			'url' => RequestMethods::post('url'),
    			'category' => \Ad::setCategories(RequestMethods::post('category')),
    			'image' => $img,
                'type' => RequestMethods::post('type', 'article'),
    			'live' => false
    		]);

    		if (!$campaign->validate()) {
    			return $view->set("errors", $campaign->errors);
    		}
    		$campaign->save();
            $commission = new \Commission([
                'ad_id' => $campaign->_id,
                'model' => RequestMethods::post('model'),
                'rate' => $this->currency(RequestMethods::post('rate')),
                'coverage' => RequestMethods::post('coverage')
            ]);
            $commission->save();

    		$this->redirect("/campaign/manage.html");
    	}
    }

    /**
     * @before _secure
     */
    public function manage() {
    	$this->seo(['title' => 'Campaign Manage', 'description' => 'Manage campaigns']);
    	$view = $this->getActionView();

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 30);
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [
            "org_id = ?" => $this->org->id,
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];

        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);
        if (in_array($property, ["user_id", "url", "title", "live"])) {
            $query["{$property} = ?"] = $value;
        }
    	$campaigns = \Ad::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query);
        $categories = Utils::categories();

    	$view->set("campaigns", $campaigns)
            ->set("count", $count)
            ->set("limit", $limit)
            ->set("page", $page)
            ->set("start", $start)
            ->set("end", $end)
            ->set("property", $property)
            ->set("value", $value)
            ->set("categories", $categories);
    }

    /**
     * @before _secure
     */
    public function edit($id) {
        $this->seo(['title' => 'Campaign Edit', 'description' => 'Edit the campaign']);
        $view = $this->getActionView();

        $c = \Ad::first(["_id = ?" => $id, "user_id = ?" => $this->user->_id]);
        $comm = \Commission::first(["ad_id = ?" => $c->_id]);
        $categories = \Category::all(['org_id' => $this->org->_id], ['name', '_id']);
        $view->set('adCategories', $categories);

        $fields = $c->render(['title', 'description', 'url']);
        if (RequestMethods::type() == 'POST') {
            foreach ($fields as $key => $value) {
                $c->$key = RequestMethods::post($key, $c->$key);
            }

            $img = $c->image;
            if ($_FILES['image']['name']) {
                $img = $this->_upload('image', 'images', ['extension' => 'jpe?g|gif|bmp|png|tif']);
                @unlink(APP_PATH . '/public/assets/uploads/images/' . $c->image);
            }
            $c->image = $img;
            $c->coverage = RequestMethods::post('coverage', ['ALL']);
            $c->category = \Ad::setCategories(RequestMethods::post('category', [$categories[0]->getMongoID()]));

            if (!$c->validate()) {
                $view->set("errors", $c->errors);
                $view->set("message", "Validation Failed");
            } else {
                $c->save();
                $comm->model = RequestMethods::post('model', 'cpc');
                $comm->rate = RequestMethods::post('rate', 0.15);

                $comm->bid = round(0.20 / 66.76, 6);
                if ($comm->rate == 0.15) {
                    $comm->bid = 0.20;
                } else if ($comm->rate == 0.16) {
                    $comm->bid = 0.25;
                }
                $comm->save();

                $view->set("message", "Campaign updated!!");
            }
        }

        $view->set("c", $c)
            ->set("comm", $comm)
            ->set('fields', $fields);

    }

    /**
     * @before _secure
     */
    public function update($cid) {
        $this->JSONView();
        $view = $this->getActionView();
        $c = \Ad::first(["_id = ?" => $cid, "org_id = ?" => $this->org->_id]);
        if (!$c || RequestMethods::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }

        foreach ($_POST as $key => $value) {
            $c->$key = $value;
        }
        $c->save();
        $view->set('message', 'Updated successfully!!');
    }

    /**
     * @before _secure
     */
    public function delete($id) {
        $this->JSONView(); $view = $this->getActionView();
        if (RequestMethods::type() !== "DELETE") {
            return $view->set('message', 'Invalid Request!!');
        }
        $ad = \Ad::first(["_id = ?" => $id, "user_id = ?" => $this->user->_id]);
        if (!$ad) return $view->set('message', 'Invalid Request!!');

        $stats = Registry::get("MongoDB")->clicks;
        $record = $stats->findOne(["adid" => $ad->_id]);
        if ($record) {
            return $view->set('message', 'Can not delete!! Campaign contain stats');
        }
        @unlink(APP_PATH . '/public/assets/uploads/images/' . $ad->image);
        $ad->delete();
        $com = \Commission::first(["ad_id = ?" => $ad->_id]);
        $com->delete();
        $view->set('message', 'Campaign removed successfully!!');
    }

    /**
     * @before _secure
     */
    public function import() {
        $this->seo(['title' => 'Campaign Import', 'description' => 'Create a new campaign']);
        $view = $this->getActionView(); $org = $this->org;

        $advertisers = \User::all(["org_id = ?" => $this->org->_id, 'type = ?' => 'advertiser'], ['_id', 'name']);
        if (count($advertisers) < 1) {
            $this->redirect('/advertiser/add.html');
        }
        $view->set('advertiser', $advertisers);
        
        if (RequestMethods::type() === 'POST') {
            if (!isset($org->meta['model']) || !isset($org->meta['rate'])) {
                return $view->set('message', 'Please update <a href="/admin/settings">Commission Settings</a>');
            }
            $a = $advertisers[0];
            $advert_id = RequestMethods::post('advert_id', $a->getMongoID($a->_id));
            $advert = \User::first(['_id = ?' => $advert_id, 'type = ?' => 'publisher']);
            if (!$advert) return $view->set('message', 'Invalid Request!!');
            if (!isset($advert->meta['campaign'])) {
                return $view->set('message', 'Please Update Advertiser campaign settings!!');
            }

            $csv = $_FILES['csv'];

            if ($csv['error'] > 0) {
                return $view->set('message', 'Error uploading csv file!!');
            }
            if ($csv['type'] !== 'text/csv') {
                return $view->set('message', 'Invalid CSV file!!');
            }
            $tmp = $csv['tmp_name'];

            $file = APP_PATH .'/uploads/'. uniqid() . '.csv';
            if (!move_uploaded_file($tmp, $file)) {
                return $view->set('message', 'Error uploading csv file!!');
            }

            $meta = new \Meta([
                'prop' => 'campImport',
                'propid' => $this->user->_id
            ]); $data = [];

            $data['advert'] = new \MongoId($advert_id);
            $data['urls'] = [];
            
            $fp = fopen($file, 'r');
            while (($line = fgetcsv($fp)) !== false) {
                $link = $line[0];
                if (!$link) continue;
                $data['urls'][] = $link;
            }
            fclose($fp);
            unlink($file);

            $meta->value = $data;
            $meta->save();
            $view->set('message', 'Campaigns Imported we will process them shortly!!');
        }
    }

    public function resize($image, $width = 600, $height = 315) {
        $path = APP_PATH . "/public/assets/uploads/images";$cdn = CDN;
        $image = base64_decode($image);
        if ($image) {
            $filename = pathinfo("${path}/${image}", PATHINFO_FILENAME);
            $extension = pathinfo("${path}/${image}", PATHINFO_EXTENSION);
            if (!$extension) $extension = "jpg";

            $thumbnail = "{$filename}-{$width}x{$height}.{$extension}";
            if (!file_exists("{$path}/{$thumbnail}")) {
                $imagine = new \Imagine\Gd\Imagine();
                $size = new \Imagine\Image\Box($width, $height);
                $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                $imagine->open("{$path}/{$image}")->thumbnail($size, $mode)->save("{$path}/resize/{$thumbnail}");

            }
            $this->redirect("{$cdn}uploads/images/resize/{$thumbnail}");
        } else {
            $this->redirect("{$cdn}img/logo.png");
        }
    }
}
