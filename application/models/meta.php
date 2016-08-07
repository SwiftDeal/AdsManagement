<?php

/**
 * @author Hemant Mann
 */
class Meta extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type text
     * @index
     */
    protected $_prop;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_propid;

    /**
     * @column
     * @readwrite
     * @type array
     * @index
     */
    protected $_value;
}
