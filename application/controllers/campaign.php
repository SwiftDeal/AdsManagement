<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use WebBot\Core\Bot as Bot;
use \Curl\Curl;
use Shared\Utils as Utils;
use \Shared\Services\Db as Db;

class Campaign extends Admin {
    
    public function info($id) {
        $type = ($this->user) && ($this->user->type === "publisher" || $this->user->type === "advertiser");
        if (!$type) {
            $this->_404();
        } else {
            $this->setLayout("layouts/".$this->user->type);
        }
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $ad = \Ad::first(["_id = ?" => $id, 'org_id' => $this->org->_id]);
        if (!$ad) $this->_404();
        $this->seo(array("title" => $ad->title));
        $view = $this->getActionView();

        $commission = Commission::first(["ad_id = ?" => $id]);

        $view->set("ad", $ad);
        $view->set("c", $commission);
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set('commission', $this->user->commission())
            ->set('tdomains', \Shared\Services\User::trackingLinks($this->user, $this->org));
    }

    /**
     * @before _secure
     */
    public function details($id) {
        $ad = \Ad::first(["_id = ?" => $id, 'org_id' => $this->org->_id]);
        if (!$ad) $this->_404();

        $this->seo(array("title" => $ad->title));
        $view = $this->getActionView();

        $start = RequestMethods::get("start", date('Y-m-d', strtotime("-1 day")));
        $end = RequestMethods::get("end", date('Y-m-d'));

        $clicks = Db::query('Click', [
            'adid' => $ad->_id, 'is_bot' => false,
            'created' => Db::dateQuery($start, $end)
        ], ['adid', 'country']);

        $advertisers = \User::all(['type' => 'advertiser', 'org_id' => $this->org->_id]);
        $advertPerf = $this->perf($clicks, ['type' => 'advertiser'], ['start' => $start, 'end' => $end]);
        $view->set('advertPerf', $advertPerf)
            ->set('advertisers', \User::objectArr($advertisers, ['_id', 'name']));

        $cf = Registry::get("configuration")->parse("configuration/cf")->cloudflare;
        $view->set("domain", $cf->api->domain);

        $comms = Commission::all(["ad_id = ?" => $id]);$models = [];
        foreach ($comms as $comm) {
            $models[] = $comm->model;
        }
        $advertiser = User::first(["id = ?" => $ad->user_id], ['name']);
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);

        $view->set("ad", $ad)
            ->set("comms", $comms)
            ->set("categories", $categories)
            ->set("advertiser", $advertiser)
            ->set('models', $models)
            ->set("start", $start)
            ->set("end", $end);
    }

    
    /**
     * @before _secure
     */
    public function contest($id = null) {
        $this->seo(['title' => 'Contest', 'description' => 'campaign contest']);
        $view = $this->getActionView();

        if (RequestMethods::type() === 'POST') {
            $msg = \Contest::updateContests($this);
            $view->set($msg);
        }

        if (RequestMethods::type() === 'DELETE') {
            $contest = \Contest::deleteAll(['_id' => $id]);
            $view->set('message', 'Contest removed!!');
        }
        $contests = \Contest::all(["org_id = ?" => $this->org->_id]);

        $view->set('contests', $contests);
    }

    protected function _create() {
        $this->seo(['title' => 'Campaign Create', 'description' => 'Create a new campaign']);
        $session = Registry::get('session'); $view = $this->getActionView();
        $advertisers = \User::isEmpty([ "org_id = ?" => $this->org->_id, 'type = ?' => 'advertiser', 'live' => true ], ['_id', 'name'], [
            'msg' => 'Please Add an Advertiser!!',
            'controller' => $this, 'redirect' => '/advertiser/add.html'
        ]);
        $view->set('advertisers', $advertisers);

        $categories = \Category::isEmpty(['org_id' => $this->org->_id], ['name', '_id'], [
            'msg' => 'Please Set Categories!!',
            'controller' => $this, 'redirect' => '/admin/settings.html'
        ]);
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
    }

    /**
     * @before _secure
     */
    public function create() {
    	$this->_create(); $view = $this->getActionView();

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
            $expiry = RequestMethods::post('expiry');
    		$campaign = new \Ad([
                'user_id' => RequestMethods::post('advert_id'),
    			'title' => RequestMethods::post('title'),
    			'description' => RequestMethods::post('description'),
                'org_id' => $this->org->_id,
    			'url' => RequestMethods::post('url'),
    			'category' => \Ad::setCategories(RequestMethods::post('category')),
    			'image' => $img,
                'type' => RequestMethods::post('type', 'article'),
                'device' => RequestMethods::post('device', ['all']),
    			'live' => false
    		]);

            if ($expiry) {
                $campaign->expiry = $expiry;
            }

    		if (!$campaign->validate()) {
    			return $view->set("errors", $campaign->errors);
    		}
    		$campaign->save();
            $models = RequestMethods::post('model');
            $comm_desc = RequestMethods::post('comm_desc');
            $revenue = RequestMethods::post('revenue');
            $rate = RequestMethods::post('rate');
            $coverage = RequestMethods::post('coverage');
            foreach ($models as $key => $value) {
                $commission = new \Commission([
                    'ad_id' => $campaign->_id,
                    'description' => $comm_desc[$key],
                    'model' => $value,
                    'rate' => $this->currency($rate[$key]),
                    'revenue' => $this->currency($revenue[$key]),
                    'coverage' => $coverage[$key]
                ]);
                $commission->save();
            }

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
        $end = RequestMethods::get("end", null);
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 30);
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = ["org_id = ?" => $this->org->id];

        if ($end) {
            $query["created"] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        }

        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", 0);
        if (in_array($property, ["user_id", "live"])) {
            $query[$property] = $value;
        } else if (in_array($property, ["url", "title"])) {
            $query[$property] = Utils::mongoRegex(preg_quote($value));
        }
    	$campaigns = \Ad::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query);
        $categories = \Category::all(['org_id' => $this->org->_id], ['_id', 'name']);
        $active = \Ad::count(["org_id = ?" => $this->org->id, "live = ?" => 1]);
        $inactive = \Ad::count(["org_id = ?" => $this->org->id, "live = ?" => 0]);

    	$view->set("campaigns", $campaigns)
            ->set("count", $count)
            ->set("active", $active)
            ->set("inactive", $inactive)
            ->set("limit", $limit)
            ->set("page", $page)
            ->set("start", $start)
            ->set("end", $end)
            ->set("property", $property)
            ->set("value", $value)
            ->set("categories", $categories);
    }

    protected function categories($category) {
        $array = [];$multi = [];
        $categories = \Category::all(['org_id' => $this->org->_id], ['name', '_id']);
        foreach ($category as $cat) {
            $array[] = Shared\Utils::getMongoID($cat);
        }
        return [
            "ids" => $array,
            "categories" => $categories
        ];
    }

    /**
     * @before _secure
     */
    public function edit($id) {
        $c = \Ad::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if(!$c) $this->redirect("/campaign/manage.html");
        $this->seo(['title' => 'Edit '.$c->title, 'description' => 'Edit the campaign']);
        $view = $this->getActionView();

        $categories = \Category::all(['org_id' => $this->org->id], ['_id', 'name']);
        if (RequestMethods::get("action") == "commdel") {
            $comm = \Commission::first(["id = ?" => RequestMethods::get('id')]);
            if ($comm) {
                $comm->delete();
                $view->set("message", "Commission deleted!!");
            }
        }

        if (RequestMethods::post("action") == "adedit") {
            $img = $c->image;
            if ($_FILES['image']['name']) {
                $img = $this->_upload('image', 'images', ['extension' => 'jpe?g|gif|bmp|png|tif']);
                @unlink(APP_PATH . '/public/assets/uploads/images/' . $c->image);
            }
            $c->image = $img;
            $c->category = \Ad::setCategories(RequestMethods::post('category'));
            $c->title = RequestMethods::post('title');
            $c->description = RequestMethods::post('description');
            $c->device = RequestMethods::post('device', ['all']);
            
            $expiry = RequestMethods::post('expiry');
            if ($expiry) {
                $c->expiry = $expiry;
            }

            if (!$c->validate()) {
                $view->set("errors", $c->errors);
                $view->set("message", "Validation Failed");
            } else {
                $c->save();
                $view->set("message", "Campaign updated!!");
            }
        }
        if (RequestMethods::post("action") == "commedit") {
            $comm = \Commission::first(["id = ?" => RequestMethods::post('cid')]);
            $comm->model = RequestMethods::post('model');
            $comm->description = RequestMethods::post('description');
            $comm->rate = $this->currency(RequestMethods::post('rate'));
            $comm->revenue = $this->currency(RequestMethods::post('revenue'));
            $comm->coverage = RequestMethods::post('coverage', ['ALL']);

            $comm->save();
            $view->set("message", "Commission updated!!");
        }

        if (RequestMethods::post("action") == "commadd") {
            $commission = new \Commission([
                'ad_id' => $c->_id,
                'description' => RequestMethods::post('description'),
                'model' => RequestMethods::post('model'),
                'rate' => $this->currency(RequestMethods::post('rate')),
                'revenue' => $this->currency(RequestMethods::post('revenue')),
                'coverage' => RequestMethods::post('coverage', ['ALL'])
            ]);
            $commission->save();
            $view->set("message", "Commission added!!");
        }

        $comms = \Commission::all(["ad_id = ?" => $c->_id]);

        $view->set("c", $c)
            ->set('categories', $categories)
            ->set("countries", Shared\Markup::countries())
            ->set("comms", $comms);

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
        $view->set('campaign', $c);
    }

    /**
     * @before _secure
     */
    public function delete($id) {
        parent::delete($id); $view = $this->getActionView();
        $ad = \Ad::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$ad) return $view->set('message', 'Invalid Request!!');

        $msg = $ad->delete();
        $view->set($msg);
    }

    /**
     * @before _secure
     */
    public function import() {
        $this->seo(['title' => 'Campaign Import', 'description' => 'Create a new campaign']);
        $view = $this->getActionView(); $org = $this->org;

        $advertisers = \User::all(["org_id = ?" => $this->org->_id, 'type = ?' => 'advertiser'], ['_id', 'name']);
        if (count($advertisers) === 0) {
            $this->redirect('/advertiser/add.html');
        } $platforms = \Platform::rssFeeds($this->org);
        $view->set('advertiser', $advertisers);

        $action = RequestMethods::post('action', '');
        switch ($action) {
            case 'campImport':
                $this->_import($org, $advertisers, $view);
                break;
            
            case 'platform':
                $pid = RequestMethods::post('pid');
                $p = $platforms[$pid]; $meta = $p->meta;
                $meta['rss']['url'] = RequestMethods::post('url');
                $parsing = (boolean) ((int) RequestMethods::post('parsing', "1"));
                $meta['rss']['parsing'] = $parsing;
                
                $p->meta = $meta;
                $p->save();

                $view->set('message', 'Updated Rss feed');
                break;

            case 'newRss':
                $url = RequestMethods::post('rss_link');
                $a = array_values($advertisers)[0];
                $advert_id = RequestMethods::post('advert_id', $a->getMongoID());
                $advert = \User::first(['_id = ?' => $advert_id, 'type = ?' => 'advertiser']);
                if (!$advert) return $view->set('message', 'Invalid Request!!');

                // try to find a platform for the given advertiser
                $domain = parse_url($url, PHP_URL_HOST); $regex = preg_quote($domain);
                $p = \Platform::first(['user_id' => $advert_id, 'url' => Utils::mongoRegex($regex)]);

                $msg = "RSS Feed Added. Campaigns Will be imported within an hour";
                try {
                    // Now schedule importing of campaigns
                    $result = \Shared\Rss::getFeed($url);
                    $rate = RequestMethods::post('rate', 0.20);
                    $revenue = RequestMethods::post('revenue', 0.25);
                    $rss = [
                        'url' => $url, 'parsing' => true, 'lastCrawled' => $result['lastCrawled'],
                        'campaign' => [
                            'model' => RequestMethods::post('model', 'cpc'),
                            'rate' => $this->currency($rate),
                            'revenue' => $this->currency($rate)
                        ]
                    ];

                    // if platform not found then add new
                    if (!$p) {
                        $p = new \Platform([
                            'url' => $domain,
                            'user_id' => $advert_id
                        ]);
                    }
                    $meta = $p->meta; $meta['rss'] = $rss;
                    $p->meta = $meta; $p->save();

                    \Meta::campImport($this->user->_id, $advert_id, $result['urls'], $rss['campaign']);
                } catch (\Exception $e) {
                    $msg = "Internal Server Error!!";
                }
                $view->set('message', $msg);
                break;
        }
        $platforms = \Platform::rssFeeds($this->org);
        $view->set('platforms', $platforms);
    }

    protected function _import($org, $advertisers, &$view) {
        if (!isset($org->meta['model']) || !isset($org->meta['rate'])) {
            return $view->set('message', 'Please update <a href="/admin/settings">Commission Settings</a>');
        }
        $a = array_values($advertisers)[0];
        $advert_id = RequestMethods::post('advert_id', $a->getMongoID());
        $advert = \User::first(['_id = ?' => $advert_id, 'type = ?' => 'advertiser']);
        if (!$advert) return $view->set('message', 'Invalid Request!!');
        if (!isset($advert->meta['campaign'])) {
            return $view->set('message', 'Please Update Advertiser campaign settings!!');
        }

        $csv = $_FILES['csv']; $tmp = $csv['tmp_name'];
        if ($csv['type'] !== 'text/csv') {
            return $view->set('message', 'Invalid CSV file!!');
        }
        
        $file = APP_PATH .'/uploads/'. uniqid() . '.csv';
        if ($csv['error'] > 0 || !move_uploaded_file($tmp, $file)) {
            return $view->set('message', 'Error uploading csv file!!');
        }
        
        $fp = fopen($file, 'r'); $urls = [];
        while (($line = fgetcsv($fp)) !== false) {
            $link = $line[0];
            if (!$link) continue;
            $urls[] = $link;
        }
        fclose($fp); unlink($file);

        \Meta::campImport($this->user->_id, $advert_id, $urls);
        $view->set('message', 'Campaigns Imported we will process them within an hour!!');
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
