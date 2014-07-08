<?php

namespace mailtank\tests\unit;

use Yii;
use mailtank\MailtankException;
use mailtank\models\MailtankLayout;
use mailtank\models\MailtankMailing;
use mailtank\models\MailtankSubscriber;
use mailtank\models\Mailtank2Email;
use mailtank\helpers\SubscribeTemplatesHelper;
use mailtank\helpers\MailtankHelper;
use mailtank\tests\helpers\TemplatesHelperTest;

class TemplatesTest extends \PHPUnit_Framework_TestCase 
{
    private static $subscribers = [];

    private function clearUnusedData()
    {
        TemplatesHelperTest::clearUnusedData();

        foreach (self::$subscribers as $subscriberId) {
            $subscriber = MailtankSubscriber::findByPk($subscriberId);
            $this->assertTrue($subscriber->delete());

            Mailtank2Email::deleteAll('mailtankId = :mailtankId', [':mailtankId'=>$subscriberId]);
        }
        self::$subscribers = array();
    }

    public function testCreate()
    {
        try {
            TemplatesHelperTest::createSubscribeTemplates(Yii::$app->mailtank->templatesPath, Yii::$app->mailtank->templatePrefix, false);
        } catch (\Exception $e) {
            $this->clearUnusedData();
            throw $e;
        }

        $layoutIds = TemplatesHelperTest::getLayoutsIds();
        $this->assertNotEmpty($layoutIds, 'Templates didnt create');
        $layoutId = array_shift($layoutIds);

        // Create subscribers and tags
        $emails = [];
        $tags = ['test_tag_'.uniqid()];
        for ($i = 2; $i > 0; $i--) {
            $id = uniqid();
            $email = $id.'@example.com';
            $subscriber = MailtankHelper::createSubscriber($email, $id, 'name_'.$id, $tags);
            if ($subscriber) {
                self::$subscribers[] = $subscriber->id;
                $emails[] = $email;
            }
        }

        $res = Yii::$app->mailtank->sendSubscribeToMailtank(
            'subfolder\mail',
            'Test subscribe',
            [],
            $tags,
            $emails);
        if ($res !== true) {
            $this->clearUnusedData();
            $this->fail($res);
        }

        $res = Yii::$app->mailtank->sendSingleMailToMailtank(
            $emails[0],
            'subfolder\mail',
            'Test single mail',
            [],
            $tags
        );
        if ($res !== true) {
            $this->clearUnusedData();
            $this->fail($res);
        }

        $this->clearUnusedData();
    }
}
