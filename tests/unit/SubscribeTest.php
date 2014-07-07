<?php

/*namespace mailtank\tests\unit;

use \mailtank\MailtankException;
use \mailtank\models\MailtankLayout;
use \mailtank\models\MailtankSubscriber;
use \mailtank\models\MailtankMailing;

class SubscribeTest extends \PHPUnit_Framework_TestCase
{
    private static $subscribers = [];
    private static $layoutId = false;

    public static function createBasicModel()
    {
        // Create subscribers and tags
        $tags = ['test_tag_' . uniqid()];

        for ($i = 2; $i > 0; $i--) {
            $subscriber = SubscriberTest::createBasicModel();
            $subscriber->tags = $tags;
            $res = $subscriber->save();
            if (!$res) {
                print_r($subscriber->getErrors());
                $this->assertTrue(false);
            }
            self::$subscribers[] = $subscriber->id;
        }

        $layout = LayoutTest::createBasicModel();
        $layout->markup = '{{some_var}} {{unsubscribe_link}}';
        $layout->subject_markup = 'Hello';
        $res = $layout->save();
        if (!$res) {
            print_r($layout->getErrors());
            $this->assertTrue(false);
        }
        self::$layoutId = $layout->id;

        $model = new MailtankMailing();
        $model->setAttributes([
            'layout_id'         => $layout->id,
            'context'           => ['some_var' => 'some value'],
            'tags'              => $tags,
            'subscribers'       => self::$subscribers,
            'unsubscribe_tags'  => $tags,
//            'tags_union' => true,
//            'tags_and_receivers_union' => true,
//            'unsubscribe_link'
//            'attachments'
        ]);

        self::assertTrue($model->validate());

        return $model;
    }

    private function clearUnusedData()
    {
        foreach (self::$subscribers as $subscriberId) {
            $subscriber = MailtankSubscriber::findByPk($subscriberId);
            $this->assertTrue($subscriber->delete());
        }
        self::$subscribers = array();

        if (self::$layoutId !== false) {
            $layout = new MailtankLayout();
            $layout->id = self::$layoutId;
            $this->assertTrue($layout->delete());
            self::$layoutId = false;
        }
    }

    public function testCreate()
    {
        $model = self::createBasicModel();
        $res = $model->save();
        if (!$res) {
            print_r($model->getErrors());
            $this->assertTrue(false);
        }

        $this->assertContains($model->status, array('ENQUEUED', 'SUCCEEDED', 'FAILED'));
        $this->clearUnusedData();
    }

    public function testGetById()
    {
        $savedModel = self::createBasicModel();
        $res = $savedModel->save();
        if (!$res) {
            print_r($savedModel->getErrors());
            $this->assertTrue(false);
        }
        $model = MailtankMailing::findByPk($savedModel->id);
        $this->assertNotEmpty($model);
        $this->clearUnusedData();
    }


    public function testRefresh()
    {
        $savedModel = self::createBasicModel();

        $e = false;
        try {
            $savedModel->refresh();
        } catch (MailtankException $e) {
            $e = true;
        }
        $this->assertTrue($e, 'Updated model cant be refreshed');
        $res = $savedModel->save();
        if (!$res) {
            print_r($savedModel->getErrors());
            $this->assertTrue(false);
        }
        $this->assertTrue($savedModel->refresh());
        $this->clearUnusedData();
    }
}
*/