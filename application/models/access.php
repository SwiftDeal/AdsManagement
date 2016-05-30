<?php

/**
 * Misc Class
 *
 * @author Hemant Mann
 */
class Access extends \Shared\Model {
    
    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     * @validate required, numeric
     */
    protected $_property_id;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 100
     *
     * @validate required
     * @label Property
     */
    protected $_property;

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     * @validate required, numeric
     */
    protected $_user_id;

}
