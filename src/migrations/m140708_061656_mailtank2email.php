<?php

use yii\db\Schema;
use yii\db\Migration;

class m140708_061656_mailtank2email extends Migration
{
    public function up()
    {
        $this->createTable(
            'mailtank2email',
            [
                'id' => 'pk',
                'email' => 'VARCHAR(128) NOT NULL',
                'mailtankId' => 'VARCHAR(128) NOT NULL',
            ]
        );
        $this->createIndex('mailtankId', 'mailtank2email', 'mailtankId', true);
    }

    public function down()
    {
        $this->dropTable('mailtank2email');
        return false;
    }
}