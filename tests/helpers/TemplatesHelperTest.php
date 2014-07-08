<?php

namespace mailtank\tests\helpers;

use mailtank\models\MailtankLayout;
use mailtank\helpers\SubscribeTemplatesHelper;

class TemplatesHelperTest extends SubscribeTemplatesHelper
{
    private static $layoutIds = [];

    /**
     * Creating ID to template for mailtank
     */
    protected static function createLayoutId($templateName, $prefix)
    {
        $id = parent::createLayoutId($templateName, $prefix);
        self::$layoutIds[] = $id;
        return $id;
    }

    public static function clearUnusedData()
    {
        foreach (self::$layoutIds as $layoutId) {
            $layout = new MailtankLayout();
            $layout->id = $layoutId;
            $layout->delete();
        }
        self::$layoutIds = [];
    }

    public static function getLayoutsIds()
    {
        return self::$layoutIds;
    }
}