<?php

namespace mailtank\tests\unit;

use Yii;
use mailtank\MailtankException;
use mailtank\models\MailtankLayout;
use mailtank\helpers\SubscribeTemplatesHelper;

class TemplatesTest extends \PHPUnit_Framework_TestCase
{
    private static $layoutId = false;

    public static function createBasicModel()
    {
        $model = new MailtankLayout();
        $id = uniqid();
        $model->setAttributes([
            'id'                => $id,
            'name'              => 'test Layout '.$id,
            'markup'            => 'Hello, {{username}}! {{unsubscribe_link}}',
            'subject_markup'    => 'Hello, {{username}}!',
        ]);

        return $model;
    }

    private function clearUnusedData()
    {
        if (self::$layoutId !== false) {
            $layout = new MailtankLayout();
            $layout->id = self::$layoutId;
            $this->assertTrue($layout->delete());
            self::$layoutId = false;
        }
    }

    public function testCreate()
    {
        SubscribeTemplatesHelper::createSubscribeTemplates(Yii::$app->mailtank->templatesPath, Yii::$app->mailtank->templatePrefix);
        //Yii::$app->mailtank->createSubscribeTemplates();
/*        $layout = self::createBasicModel();
        $unsavedModel = clone $layout;

        $res = $layout->save();
        if (!$res) {
            print_r($layout->getErrors());
            $this->assertTrue(false);
        }
        self::$layoutId = $layout->id;

        $this->assertEquals($unsavedModel->id, $layout->id);
        $this->assertEquals('test Layout '.$layout->id, $layout->name);
        $this->assertEquals('Hello, {{username}}! {{unsubscribe_link}}', $layout->markup);
        $this->assertEquals('Hello, {{username}}!', $layout->subject_markup);

        $this->clearUnusedData();*/
    }
}
