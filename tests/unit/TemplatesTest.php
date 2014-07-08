<?php

namespace mailtank\tests\unit;

use Yii;
use mailtank\MailtankException;
use mailtank\models\MailtankLayout;
use mailtank\helpers\SubscribeTemplatesHelper;

class TemplatesTest extends \PHPUnit_Framework_TestCase 
{
    private function clearUnusedData()
    {
    }

    public function testCreate()
    {
        try {
            TemplatesTestHelper::createSubscribeTemplates(Yii::$app->mailtank->templatesPath, Yii::$app->mailtank->templatePrefix, false);
        } catch (\Exception $e) {
            $this->clearUnusedData();
            throw $e;
        }
    }
}
