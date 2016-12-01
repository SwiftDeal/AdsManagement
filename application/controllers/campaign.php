<?php

/**
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use WebBot\Core\Bot as Bot;
use \Curl\Curl;
use Shared\Utils as Utils;
use \Shared\Services\Db as Db;
use Framework\ArrayMethods as ArrayMethods;
use Framework\RequestMethods as RM;
use YTDownloader\Service\Download as Downloader;

class Campaign extends Admin {
    protected function _commissionDel($ad, &$view) {
        if (RM::get('action') !== 'commDel') return $view->set('message', 'Invalid Request!!');
        $commId = RM::get('comm_id');
        $commCount = Commission::count(['ad_id' => $ad->_id]);
        if ($commCount < 2) {
            return $view->set('message', 'Atleast 1 commission is required!!');
        }
        $comm = Commission::first(['_id' => $commId, 'ad_id' => $ad->_id]);
        if ($comm) $comm->delete();
        $view->set('message', 'Commission deleted!!');
    }

    /**
     * @before _secure
     */
    public function details($id) {
        $ad = \Ad::first(['_id' => $id, 'org_id' => $this->org->_id]);
        if (!$ad) $this->_404();

        $this->seo(array("title" => $ad->title));
        $view = $this->getActionView();

        $start = RM::get("start", date('Y-m-d', strtotime("-1 day")));
        $end = RM::get("end", date('Y-m-d'));

        if (RM::type() === 'DELETE') {
            $this->_commissionDel($ad, $view);
        }

        switch (RM::post("action")) {
            case 'caccess':
                $meta = $ad->meta;
                $meta["access"] = RM::post("access");
                $ad->meta = $meta;
                $ad->save();
                break;
        }

        switch (RM::get("action")) {
            case 'updateAccess':
                $access = AdAccess::first(["id = ?" => RM::get("access_id")]);
                if ($access) {
                    $access->live = RM::get("live", 0);
                    $access->save();
                    $this->redirect("/campaign/details/".$ad->id.".html#permission");
                    $session->set('$flashMessage', 'Access Updated');
                }
                break;
        }

        $comms = Commission::all(["ad_id = ?" => $id]);
        $models = ArrayMethods::arrayKeys($comms, 'model');
        $advertiser = User::first(["id = ?" => $ad->user_id], ['name']);
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);
        $publishers = \User::all(['type' => 'publisher', 'org_id' => $this->org->_id, "live = ?" => true], ["id", "name"]);
        $adaccess = \AdAccess::all(['org_id' => $this->org->_id, "ad_id = ?" => $ad->id]);

        $view->set("ad", $ad)
            ->set("comms", $comms)
            ->set("categories", $categories)
            ->set("advertiser", $advertiser)
            ->set('publishers', \User::objectArr($publishers, ['_id', 'name']))
            ->set('models', $models)
            ->set('adaccess', $adaccess)
            ->set("start", $start)
            ->set("end", $end);
    }

    
    /**
     * @before _secure
     */
    public function contest($id = null) {
        $this->seo(['title' => 'Contest', 'description' => 'campaign contest']);
        $view = $this->getActionView();

        if (RM::type() === 'POST') {
            $msg = \Contest::updateContests($this);
            $view->set($msg);
        }

        if (RM::type() === 'DELETE') {
            $contest = \Contest::deleteAll(['_id' => $id]);
            $view->set('message', 'Contest removed!!');
        }
        $contests = \Contest::all(["org_id = ?" => $this->org->_id]);

        $view->set('contests', $contests);
    }

    protected function _create() {
        $this->seo(['title' => 'Campaign Create', 'description' => 'Create a new campaign']);
        $session = Registry::get('session'); $view = $this->getActionView();
        $advertisers = \User::isEmpty([ "org_id = ?" => $this->org->_id, 'type = ?' => 'advertiser', 'live' => true ], ['_id', 'name', 'meta'], [
            'msg' => 'Please Add an Advertiser!!',
            'controller' => $this, 'redirect' => '/advertiser/add.html'
        ]);
        $view->set('advertisers', User::objectArr($advertisers, ['_id', 'name', 'meta']));

        $categories = \Category::isEmpty(['org_id' => $this->org->_id], ['name', '_id'], [
            'msg' => 'Please Set Categories!!',
            'controller' => $this, 'redirect' => '/admin/settings.html'
        ]);
        $view->set('categories', $categories);

        $link = RM::get("link");
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
            ->set("type", RM::get("type"))
            ->set("errors", []);
    }

    /**
     * @before _secure
     */
    public function create() {
    	$this->_create(); $view = $this->getActionView();

    	if (RM::type() == 'POST') {
            $img = null;
            // give preference to uploaded image
            $img = $this->_upload('image', 'images', ['extension' => 'jpe?g|gif|bmp|png|tif']);
    		if (!$img) {
                $img_url = RM::post("image_url");
    			$img = Shared\Utils::downloadImage($img_url);
    		}

    		if (!$img) {
    			return $view->set('message', 'Failed to upload the image');
    		}
            $expiry = RM::post('expiry');
    		$campaign = new \Ad([
                'user_id' => RM::post('advert_id'),
    			'title' => RM::post('title'),
    			'description' => RM::post('description'),
                'org_id' => $this->org->_id,
    			'url' => RM::post('url'),
                'preview_url' => RM::post('preview_url'),
    			'category' => \Ad::setCategories(RM::post('category')),
    			'image' => $img,
                'type' => RM::post('type', 'article'),
                'device' => RM::post('device', ['all']),
    			'live' => false
    		]);

            $visibility = RM::post('visibility', 'public');
            if ($visibility === "private") {
                $campaign->meta = ['private' => true];
            }

            $permission = RM::post('permission', false);
            if ($permission == "yes") {
                $campaign->meta = ['permission' => true];
            }

            if ($expiry) {
                $campaign->expiry = $expiry;
            }
            try {
                if ($campaign->type === "video") {
                    $url = RM::post('videoUrl');
                    $ytdl = new Downloader($url);
                    $campaign->getMeta()['processing'] = true;
                    $campaign->getMeta()['videoUrl'] = $ytdl->getUrl();
                }
            } catch (\Exception $e) {
                // Invalid URL
                return $view->set("errors", ['videoUrl' => ["Pass a valid youtube video URL"]]);
            }

    		if (!$campaign->validate()) {
    			return $view->set("errors", $campaign->errors);
    		}
    		$campaign->save();
            $models = RM::post('model');
            $comm_desc = RM::post('comm_desc');
            $revenue = RM::post('revenue');
            $rate = RM::post('rate');
            $coverage = RM::post('coverage');
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

            Registry::get("session")->set('$flashMessage', 'Campaign Created successfully!!');
    		$this->redirect("/campaign/manage.html");
    	}
    }
    
    /**
     * @before _secure
     */
    public function manage() {
    	$this->seo(['title' => 'Campaign Manage', 'description' => 'Manage campaigns']);
    	$view = $this->getActionView();$campaigns = [];

        $page = RM::get("page", 1); $limit = RM::get("limit", 10);
        $property = RM::get("property"); $value = RM::get("value");

        switch (RM::get("sort")) {
            case 'trending':
                $start = RM::get("start", date("Y-m-d", strtotime('now')));
                $end = RM::get("end", date("Y-m-d", strtotime('now')));
                $q = ['start' => $start, 'end' => $end]; $view->set($q);
                // Only find the ads for this organizations
                $allAds = \Ad::all(['org_id' => $this->org->_id], ['_id']);
                $in = Db::convertType(array_keys($allAds));
                $clickCol = Db::collection('Click');
                $match = [
                    'created' => Db::dateQuery($start, $end),
                    'is_bot' => false,
                    'adid' => ['$in' => $in]
                ];
                $records = $clickCol->aggregate([
                    ['$match' => $match],
                    ['$project' => ['adid' => 1, '_id' => 0]],
                    ['$group' => [
                        '_id' => '$adid',
                        'count' => ['$sum' => 1]
                    ]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => (int) $limit]
                ]);
                foreach ($records as $r) {
                    $arr = Utils::toArray($r);
                    $id = Utils::getMongoID($arr['_id']);
                    $campaigns[] = Ad::first(["id = ?" => $id]);
                }
                $count = $limit;
                break;
            
            default:
                $query = ["org_id = ?" => $this->org->id];
                if (in_array($property, ["user_id", "live", "id"])) {
                    $query[$property] = $value;
                } else if (in_array($property, ["url", "title"])) {
                    $query[$property] = Utils::mongoRegex(preg_quote($value));
                }
                $campaigns = \Ad::all($query, [], 'created', 'desc', $limit, $page);
                $count = \Ad::count($query);
                break;
        }

        $categories = \Category::all(['org_id' => $this->org->_id], ['_id', 'name']);
        $active = \Ad::count(["org_id = ?" => $this->org->id, "live = ?" => 1]);
        $inactive = \Ad::count(["org_id = ?" => $this->org->id, "live = ?" => 0]);

    	$view->set("campaigns", $campaigns)
            ->set("count", $count)
            ->set("active", $active)
            ->set("inactive", $inactive)
            ->set("limit", $limit)
            ->set("page", $page)
            ->set("property", $property)
            ->set("value", $value)
            ->set("categories", $categories);
    }

    /**
     * @before _secure
     * @after _csrfToken
     */
    public function edit($id) {
        $c = \Ad::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$c) $this->redirect("/campaign/manage.html");
        $this->seo(['title' => sprintf("Edit: %s", $c->title), 'description' => 'Edit the campaign']);
        $view = $this->getActionView();

        $categories = \Category::all(['org_id' => $this->org->id], ['_id', 'name']);
        if (RM::type() === 'DELETE') {
            $this->_commissionDel($c, $view);
        }

        if (RM::type() === 'POST') {
            $action = RM::post("action");
            switch ($action) {
                case 'targeting':
                    $campaign = $c;
                    $campaign->device = RM::post('device', $campaign->device);
                    $visibility = RM::post('visibility', 'public');
                    if ($visibility === "private") {
                        $campaign->getMeta()['private'] = true;
                    } else {
                        unset($campaign->getMeta()['private']);
                    }

                    $permission = RM::post('permission', false);
                    if ($permission == "yes") {
                        $campaign->getMeta()['permission'] = true;
                    } else {
                        unset($campaign->getMeta()['permission']);
                    }
                    $campaign->save();
                    $view->set("message", "Campaign updated!!");
                    break;

                case 'adedit':
                    $c->category = \Ad::setCategories(RM::post('category'));
                    $c->title = RM::post('title');
                    $c->url = RM::post("url");
                    $c->description = RM::post('description');
                    $c->device = RM::post('device', ['all']);
                    
                    $expiry = RM::post('expiry');
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
                    break;

                case 'imageUpload':
                    $message = "Image Updated Successfully!!";
                    $img = ' '; $imgUrl = RM::post("imageUrl");
                    if ($_FILES['image']['name']) {
                        $img = Utils::media('image', 'upload');
                    } else if ($imgUrl) {
                        $img = Utils::media($imgUrl, 'download');
                    }

                    if (strlen($img) > 4) { // to check if media was uploaded successfully
                        Utils::media($c->image, 'remove');
                    } else {
                        $img = $c->image;
                        $message = "Please Upload a valid Image!!";
                    }

                    $c->image = $img; $c->save();
                    $view->set("message", $message);
                    break;
                
                case 'commedit':
                    $comm = \Commission::first(["id = ?" => RM::post('cid')]);
                    $comm->model = RM::post('model');
                    $comm->description = RM::post('description');
                    $comm->rate = $this->currency(RM::post('rate'));
                    $comm->revenue = $this->currency(RM::post('revenue'));
                    $comm->coverage = RM::post('coverage', ['ALL']);

                    if ($comm->validate()) {
                        $comm->save();
                        $view->set("message", "Commission updated!!");   
                    } else {
                        $view->set("errors", $comm->errors);
                        $view->set("message", "Validation Failed");
                    }
                    break;

                case 'commadd':
                    $commission = new \Commission([
                        'ad_id' => $c->_id,
                        'description' => RM::post('description'),
                        'model' => RM::post('model'),
                        'rate' => $this->currency(RM::post('rate')),
                        'revenue' => $this->currency(RM::post('revenue')),
                        'coverage' => RM::post('coverage', ['ALL'])
                    ]);
                    if ($commission->validate()) {
                        $commission->save();
                        $view->set("message", "Commission added!!");   
                    } else {
                        $view->set("errors", $commission->errors);
                        $view->set("message", "Validation Failed");
                    }
                    break;
            }
        }

        $comms = \Commission::all(["ad_id = ?" => $c->_id]);

        $view->set("c", $c)
            ->set('categories', $categories)
            ->set('advertisers', User::all(["org_id = ?" => $this->org->id, "type = ?" => "advertiser"], ["id", "name"]))
            ->set("comms", $comms);

    }

    /**
     * @before _secure
     */
    public function update($cid) {
        $this->JSONView(); $view = $this->getActionView();
        $c = \Ad::first(["_id = ?" => $cid, "org_id = ?" => $this->org->_id]);
        if (!$c || RM::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }
        $view->set('message', 'Updated successfully!!');

        $allowedFields = ['title', 'description', 'live', 'expiry', 'type'];
        foreach ($allowedFields as $f) {
            $c->$f = RM::post($f, $c->$f);
        }
        $processing = $c->meta['processing'] ?? false;
        if ($processing) {
            $view->set('message', 'Campaign is being processed. Try after 10 minutes.');
        } else {
            $c->save();
            $view->set('campaign', $c);
        }
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

        $action = RM::post('action', '');
        switch ($action) {
            case 'campImport':
                $this->_import($org, $advertisers, $view);
                break;
            
            case 'platform':
                $pid = RM::post('pid');
                $p = $platforms[$pid]; $meta = $p->meta;
                $meta['rss']['url'] = RM::post('url');
                $parsing = (boolean) ((int) RM::post('parsing', "1"));
                $meta['rss']['parsing'] = $parsing;
                
                $p->meta = $meta;
                $p->save();

                $view->set('message', 'Updated Rss feed');
                break;

            case 'newRss':
                $url = RM::post('rss_link');
                $a = array_values($advertisers)[0];
                $advert_id = RM::post('advert_id', $a->getMongoID());
                $advert = \User::first(['_id = ?' => $advert_id, 'type = ?' => 'advertiser']);
                if (!$advert) return $view->set('message', 'Invalid Request!!');

                // try to find a platform for the given advertiser
                $domain = parse_url($url, PHP_URL_HOST); $regex = preg_quote($domain);
                $p = \Platform::first(['user_id' => $advert_id, 'url' => Utils::mongoRegex($regex)]);

                $msg = "RSS Feed Added. Campaigns Will be imported within an hour";
                try {
                    // Now schedule importing of campaigns
                    $result = \Shared\Rss::getFeed($url);
                    $rate = RM::post('rate', 0.20);
                    $revenue = RM::post('revenue', 0.25);
                    $rss = [
                        'url' => $url, 'parsing' => true, 'lastCrawled' => $result['lastCrawled'],
                        'campaign' => [
                            'model' => RM::post('model', 'cpc'),
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
        $advert_id = RM::post('advert_id', $a->getMongoID());
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

    /**
     * @before _secure
     */
    public function settings() {
        $this->seo(array("title" => "Campaign: Settings")); $view = $this->getActionView();
        $user = $this->user; $org = $this->org;
        
        if (RM::type() == 'POST') {
            $action = RM::post('action', '');
            switch ($action) {
                case 'commission':
                    $view->set('message', 'Commission updated!!');
                    break;

                case 'commadd':
                    $this->addCommisson($org);
                    break;

                case 'domains':
                    $message = $org->updateDomains();
                    $this->setOrg($org);
                    $view->set('message', $message);
                    break;

                case 'categories':
                    $success = Category::updateNow($this->org);
                    if ($success) {
                        $msg = 'Categories updated Successfully!!';
                    } else {
                        $msg = 'Failed to delete some categories because in use by campaigns!!';
                    }
                    $view->set('message', $msg);
                    break;
            }
            $this->setUser($user);
        }
        $categories = \Category::all(['org_id' => $this->org->_id]);
        $view->set('categories', $categories);
    }

    protected function addCommisson($org) {
        $meta = $org->meta;
        if (array_key_exists('commission', $meta)) {
            $arr = $meta["commission"];
            array_push($arr, [
                'coverage' => RM::post('coverage', ['ALL']),
                'model' => RM::post('model'),
                'rate' => $this->currency(RM::post('rate'))
            ]);
            $meta["commission"] = $arr;
        } else {
            $arr = [];
            $arr[] = [
                'coverage' => RM::post('coverage', ['ALL']),
                'model' => RM::post('model'),
                'rate' => $this->currency(RM::post('rate'))
            ];
            $meta["commission"] = $arr;
        }
        
        $org->meta = $meta;
        $org->save();
    }
}
