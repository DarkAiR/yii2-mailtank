<?php

namespace mailtank\models;

use yii\db\ActiveRecord;

/**
 * Class Mailtank2Email
 * @property int id
 * @property string email
 * @property string mailtankId
 */
class Mailtank2Email extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%mailtank2email}}';
    }

    public function rules()
    {
        return [
            [['email', 'mailtankId'], 'required']
        ];
    }
}
