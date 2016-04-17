<?php

/**
 * @author Faizan Ayubi
 */
class CPC extends Shared\Model {
    
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
    protected $_item_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @label cpc value
     */
    protected $_value;
}
