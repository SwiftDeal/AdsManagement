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
     * @validate required
     */
    protected $_prop;

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     * @validate required
     */
    protected $_propid;

    /**
     * @column
     * @readwrite
     * @type array
     * @index
     * @validate required
     */
    protected $_value;

    public static function campImport($uid, $advert_id, $urls, $extra = []) {
        $uid = Utils::mongoObjectId($uid);
        $advert_id = Utils::mongoObjectId($advert_id);

        $data = [
            'prop' => 'campImport', 'propid' => $uid,
            'value' => [
                'advert_id' => $advert_id,
                'urls' => $urls
            ]
        ];

        if (!empty($extra)) {
            $data['value']['campaign'] = $extra;
        }
        $meta = new self($data);

        $meta->save();
        return $meta;
    }
}
