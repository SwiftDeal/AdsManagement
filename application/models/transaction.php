<?php

/**
 * for live = 0 credit, live = 1 debit
 * @author Faizan Ayubi
 */
class Transaction extends Shared\Model {

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
     * @type decimal
     * @length 10,2
     *
     * @validate required
     * @label amount
     */
    protected $_amount;

    /**
     * @column
     * @readwrite
     * @type text
     * @length 255
     *
     * @validate required, min(3)
     * @label reference
     */
    protected $_ref;

    /**
    * @column
    * @readwrite
    * @type text
    * @length 6
    * @index
    *
    * @validate required
    * @label credit or debit
    */
    protected $_type;
}
