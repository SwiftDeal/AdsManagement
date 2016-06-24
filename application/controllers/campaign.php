<?php

/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use WebBot\Core\Bot as Bot;
use \Curl\Curl;
use YTDownloader\Service\Download as Downloader;

class Campaign extends Publisher {
    
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

        $view->set("errors", array())
            ->set("start", strftime("%Y-%m-%d", strtotime('now')))
            ->set("end", strftime("%Y-%m-%d", strtotime('+29 day')));
        if (RequestMethods::post("action") == "content") {
            $vid_url = RequestMethods::post("video");
            try {
                if ($vid_url) {
                    $video = $this->_uploadVideo($vid_url);
                } else {
                    $video = null;
                }
            } catch (\Exception $e) {
                $view->set("errors", ["video" => [$e->getMessage()]]);
                return;
            }

            if (RequestMethods::post("image_url")) {
                $image = $this->urls3upload(RequestMethods::post("image_url"));
            } else {
                $image = $this->s3upload("image", "images");
            }

            $ad = new \Ad(array(
                "user_id" => $this->user->_id,
                "url" =>  RequestMethods::post("url"),
                "target" =>  RequestMethods::post("url"),
                "title" => RequestMethods::post("title"),
                "description" => RequestMethods::post("description", ""),
                "image" => $image,
                "category" => RequestMethods::post('category'),
                "coverage" => RequestMethods::post('coverage'),
                "budget" => RequestMethods::post("budget", 100),
                "frequency" => RequestMethods::post("frequency", 2),
                "start" => RequestMethods::post("start"),
                "end" => RequestMethods::post("end"),
                "cpc" => RequestMethods::post("cpc", 0.15),
                "visibility" => 1,
                "live" => 0
            ));

            if ($video) {
                $ad->video = $video;
                $ad->type = "video";
            }

            if ($ad->validate()) {
                $ad->save();
                
                $categories = $ad->category;
                foreach ($categories as $key => $value) {
                    $cat = new \AdCategory([
                        'ad_id' => $ad->_id,
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
    public function edit($id = NULL) {
        $this->seo(array("title" => "Edit Ad", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $ad = \Ad::first(array("_id" => $id, "user_id" => $this->user->_id));
        if (!$ad) {
            $this->redirect("/campaign/manage.html");
        }
        
        if (RequestMethods::post("action") == "update") {
            $ad->title = RequestMethods::post("title");
            $ad->url = RequestMethods::post("url");
            $ad->description = RequestMethods::post("description");
            $ad->live = 0;
            if ($ad->validate()) {
                if (is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $ad->image = $this->s3upload("image", "images");
                }
                $ad->save();
                $view->set("message", "Campaign Updated Successfully");
            }  else {
                $view->set("errors", $ad->getErrors());
            }
        }
        $view->set("ad", $ad);
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
        $where = array("{$property}" => $value);

        $contents = \Ad::all($where, array("id", "title", "modified", "image", "visibility", "created", "url", "live", "user_id"), "created", "desc", $limit, $page);
        $count = \Ad::count($where);

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
        
        $where = array("user_id" => $this->user->_id);
        
        $ads = \Ad::all($where, array("_id", "title", "created", "image", "url", "live", "visibility"), "created", -1, $limit, $page);
        $count = \Ad::count($where);

        $view->set("ads", $ads);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
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

    public function remove($id) {
        $this->noview();
        $ad = \Ad::first(["_id = ?" => $id, "user_id = ?" => $this->user->_id]);
        if (!$ad) {
            $this->redirect("/404");
        }

        $impression = \Impressions::first(["cid = ?" => $ad->_id]);
        if (!$impression && !$ad->live) {
            $ad->delete();
        }
        $this->redirect(RequestMethods::server('HTTP_REFERER', '/campaign/manage'));
    }

    /**
     * Download the video and convert it to mp4
     * Store in uploads dir
     */
    protected function _uploadVideo($url) {
        $ytdl = new Downloader($url);
        Downloader::setDownloadPath(APP_PATH. '/public/assets/uploads/videos/');

        // $format = 36 (240p), 18 (360p)
        $file = [];
        $dwnld = $ytdl->download(36, '3gp');
        // $file[] = $ytdl->download(18, 'mp4');
        
        // need to convert it to mp4
        $infile = Downloader::getDownloadPath() . $dwnld;
        $name = (array_shift(explode(".", $dwnld))) . '.mp4';
        $outfile = Downloader::getDownloadPath() . $name;

        $cmd = 'ffmpeg -i '. $infile .' -acodec libmp3lame -ar 44100 ' . $outfile;
        exec($cmd, $output, $return);
        if ($return != 0) {
            throw new \Exception("Error Converting Video to MP4", 1);
        }

        unlink($infile);
        $file[] = $name;
        
        return $file;
    }
}
