<?php

/**
 * Description of item
 *
 * @author Faizan Ayubi
 */
class Item extends Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_url;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     * @index
     */
    protected $_title;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_image;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 4,2
     */
    protected $_commission;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_category;
    
    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_description;
    
    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     */
    protected $_user_id;

    public function encode($username, $user_id) {
        $q = "id={$this->clean($this->id)}&title={$this->clean($this->title)}&description={$this->clean($this->description)}&image={$this->clean($this->image)}&url={$this->clean($this->url)}&username={$this->clean($username)}&user_id={$this->clean($user_id)}";
        return base64_encode($q);
    }

    public function clean($subject) {
        return str_replace("&", "", $subject);
    }
}
