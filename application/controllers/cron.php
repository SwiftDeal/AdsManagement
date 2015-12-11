<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Auth {

    private $connection;
    private $path;
    private $handle;
    private $cron_file;

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->noview();
    }

    protected function initialize($host=NULL, $port=NULL, $username=NULL, $password=NULL) {
        $this->path      = APP_PATH . "/logs/";
        $this->handle    = 'crontab.txt';  
        $this->cron_file = "{$this->path}{$this->handle}";
     
        try {
            if (is_null($host) || is_null($port) || is_null($username) || is_null($password)) {
                throw new Exception("Please specify the host, port, username and password!");
            }
             
            $this->connection = @ssh2_connect($host, $port);
            if (!$this->connection) {
                throw new Exception("The SSH2 connection could not be established.");
            }
     
            $authentication = @ssh2_auth_password($this->connection, $username, $password);
            if (!$authentication) {
                throw new Exception("Could not authenticate '{$username}' using password: '{$password}'.");
            }
        } catch (Exception $e) {
            $this->error_message($e->getMessage());
        }
    }

    protected function exec() {
        $argument_count = func_num_args();
        try {
            if (!$argument_count) {
                throw new Exception("There is nothing to execute, no arguments specified.");
            }
            $arguments = func_get_args();
            $command_string = ($argument_count > 1) ? implode(" && ", $arguments) : $arguments[0];
            $stream = @ssh2_exec($this->connection, $command_string);
            if (!$stream) {
                throw new Exception("Unable to execute the specified commands: <br />{$command_string}");
            }
        } catch (Exception $e) {
            $this->error_message($e->getMessage());
        }
        return $this;
    }

    protected function write_to_file($path=NULL, $handle=NULL) {
        if (!$this->crontab_file_exists()) {   
            $this->handle = (is_null($handle)) ? $this->handle : $handle;
            $this->path   = (is_null($path))   ? $this->path   : $path;
     
            $this->cron_file = "{$this->path}{$this->handle}";
            $init_cron = "crontab -l > {$this->cron_file} && [ -f {$this->cron_file} ] || > {$this->cron_file}";
            $this->exec($init_cron);
        }
     
        return $this;
    }

    protected function remove_file() {
        if ($this->crontab_file_exists()) {
            $this->exec("rm {$this->cron_file}");
        }
        return $this;
    }

    protected function append_cronjob($cron_jobs=NULL) {
        if (is_null($cron_jobs)) {
            $this->error_message("Nothing to append!  Please specify a cron job or an array of cron jobs.");
        }
        
        $append_cronfile = "echo '";
        $append_cronfile .= (is_array($cron_jobs)) ? implode("\n", $cron_jobs) : $cron_jobs;
        $append_cronfile .= "'  >> {$this->cron_file}";
        $install_cron = "crontab {$this->cron_file}";
        $this->write_to_file()->exec($append_cronfile, $install_cron)->remove_file();

        return $this;
    }

    protected function remove_cronjob($cron_jobs=NULL) {
        if (is_null($cron_jobs)) {
            $this->error_message("Nothing to remove!  Please specify a cron job or an array of cron jobs.");
        }
         
        $this->write_to_file();
        $cron_array = file($this->cron_file, FILE_IGNORE_NEW_LINES);
        if (empty($cron_array)) {
            $this->error_message("Nothing to remove!  The cronTab is already empty.");
        }
         
        $original_count = count($cron_array);
        if (is_array($cron_jobs)) {
            foreach ($cron_jobs as $cron_regex) {
                $cron_array = preg_grep($cron_regex, $cron_array, PREG_GREP_INVERT);
            }
        } else {
            $cron_array = preg_grep($cron_jobs, $cron_array, PREG_GREP_INVERT);
        }
        
        return ($original_count === count($cron_array)) ? $this->remove_file() : $this->remove_crontab()->append_cronjob($cron_array);
    }

    protected function remove_crontab() {
        $this->exec("crontab -r")->remove_file();
        return $this;
    }

    /**
     * @before _secure, _admin
     */
    public function index() {
        $this->noview();
        //$this->verify();
    }
    
    protected function verify() {
        $startdate = date('Y-m-d', strtotime("-10 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created <= ?" => $enddate
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));
        $total = Link::count($where);

        $counter = 0;
        $googl = Framework\Registry::get("googl");
        foreach ($links as $link) {
            $object = $googl->analyticsFull($link->short);
            $count = $object->analytics->day->shortUrlClicks;
            //minimum count for earning
            if ($count > 15) {
                $stat = $this->saveStats($object, $link, $count);
                $this->saveEarnings($link, $count, $stat, $object);

                //sleep the script
                if ($counter == 100) {
                    sleep(3);
                    $counter = 0;
                }
                ++$counter;
            }
        }
    }

    protected function saveStats($object, $link, $count) {
        $stat = Stat::first(array("link_id = ?" => $link->id));
        if ($stat) {
            $stat->verifiedClicks = $count;
            $stat->shortUrlClicks = $object->analytics->day->shortUrlClicks;
            $stat->longUrlClicks = $object->analytics->day->longUrlClicks;
            $stat->referrers = json_encode($object->analytics->day->referrers);
            $stat->countries = json_encode($object->analytics->day->countries);
            $stat->browsers = json_encode($object->analytics->day->browsers);
            $stat->platforms = json_encode($object->analytics->day->platforms);
        } else {
            $stat = new Stat(array(
                "user_id" => $link->user_id,
                "link_id" => $link->id,
                "verifiedClicks" => $count,
                "shortUrlClicks" => $object->analytics->day->shortUrlClicks,
                "longUrlClicks" => $object->analytics->day->longUrlClicks,
                "referrers" => json_encode($object->analytics->day->referrers),
                "countries" => json_encode($object->analytics->day->countries),
                "browsers" => json_encode($object->analytics->day->browsers),
                "platforms" => json_encode($object->analytics->day->platforms)
            ));
        }
        $stat->save();
        return $stat;
    }
    
    protected function saveEarnings($link, $count, $stat, $object) {
        $revenue = 0;$country_count = 0;$nonverified_count = 0;$verified_count = 0;

        $referrers = $object->analytics->day->referrers;
        foreach ($referrers as $referer) {
            if ($referer->id == 'chocoghar.com') {
                $nonverified_count += $referer->count;
            }
        }
        $verified_count = $count - $nonverified_count;
        $correct = 1;

        $countries = $object->analytics->day->countries;

        $rpms = RPM::all(array("item_id = ?" => $link->item_id), array("value", "country"));
        $rpms_country = array();
        $rpms_value = array();

        foreach ($rpms as $rpm) {
            $rpms_country[] = strtoupper($rpm->country);
            $rpms_value[strtoupper($rpm->country)] = $rpm->value;
        }
        foreach ($countries as $country) {
            if (in_array($country->id, $rpms_country)) {
                $revenue += $correct*($rpms_value[$country->id])*($country->count)/1000;
                $country_count += $country->count;
            }
        }
        if ($verified_count > $country_count) {
            $revenue += ($verified_count - $country_count)*$correct*($rpms_value["NONE"])/1000;
        }

        $avgrpm = round(($revenue*1000)/($count), 2);
        $earning = new Earning(array(
            "item_id" => $link->item_id,
            "link_id" => $link->id,
            "amount" => $revenue,
            "user_id" => $link->user_id,
            "stat_id" => $stat->id,
            "rpm" => $avgrpm,
            "live" => 1
        ));
        $earning->save();
    }
}
