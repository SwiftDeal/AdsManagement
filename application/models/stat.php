<?php

/**
 * Description of stat
 *
 * @author Faizan Ayubi
 */
class Stat extends Shared\Model {
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     */
    protected $_link_id;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_verifiedClicks;
    
    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_shortUrlClicks;
    
}
