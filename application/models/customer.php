<?php

/**
 * @author Faizan Ayubi
 */
class Customer extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type integer
     * @index
     * @validate required
     */
    protected $_user_id;
}