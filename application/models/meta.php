<?php

/**
 * @author Hemant Mann
 */
use Shared\Utils as Utils;
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

    public static function campImport($uid, $advert_id, $urls) {
        $uid = Utils::mongoObjectId($uid);
        $advert_id = Utils::mongoObjectId($advert_id);
        $meta = new self([
            'prop' => 'campImport', 'propid' => $uid,
            'value' => [
                'advert_id' => $advert_id,
                'urls' => $urls
            ]
        ]);

        $meta->save();
        return $meta;
    }
}
