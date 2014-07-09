<?php

namespace mailtank\helpers;

use Yii;
use mailtank\MailtankException;
use mailtank\models\Mailtank2Email;
use mailtank\models\MailtankSubscriber;
use console\Console;

class MailtankHelper
{
    /**
     * Get mailtank ID by email
     */
    public static function getExternalId($email)
    {
        // Если не проверить на is_string, то поиск в базе email=N выдаст все записи, а не ноль, как ожидается
        if (empty($email) || !is_string($email))
            return false;

        $res = Mailtank2Email::find()
            ->where(['email' => $email])
            ->asArray()
            ->one();
        if (!$res)
            return false;
        return $res['mailtankId'];
    }

    /**
     * Get email by mailtank ID
     */
    public static function getEmailByExternalId($mailtankId)
    {
        if (empty($mailtankId))
            return false;
        $res = Mailtank2Email::find()
            ->where(['mailtankId' => $mailtankId])
            ->asArray()
            ->one();
        if (!$res)
            return false;
        return $res['email'];
    }

    /**
     * Get subscriber by email
     */
    public static function getSubscriber($email)
    {
        $mailtankId = self::getExternalId($email);
        if (!$mailtankId)
            return false;
        return MailtankSubscriber::findByPk($mailtankId);
    }

    /**
     * Create new subscriber
     * @param string $email
     * @param integer $userId
     * @param string $userName
     * @param array $tags
     * @param array $props
     */
    public static function createSubscriber($email, $userId = 0, $userName = '', $tags = [], $props = [])
    {
        $subscriber = new MailtankSubscriber();
        $subscriber->email = $email;
        $subscriber->setProperty('userId', $userId);
        $subscriber->setProperty('userName', $userName);
        $subscriber->setProperty('created', time());
        $subscriber->tags = $tags;

        foreach ($props as $k=>$v)
            $subscriber->setProperty($k, $v);

        if (!$subscriber->save())
            throw new MailtankException(MailtankHelper::implodeErrors('; ', $subscriber->getErrors()));

        if (!self::createInnerSubscriber($email, $subscriber->id)) {
            // Подчищаем мейлтанк за собой, иначе там навечно зависнет не привязанный к системе пользователь
            $subscriber->delete();
            return false;
        }

        return $subscriber;
    }

    /**
     * Обновление указанных тегов и свойств. Не указанные остаются как есть.
     * Обновляем по $subscriber, т.к. почти всегда у нас он уже имеется на этот момент
     * и незачем делать лишний запрос в базу и в мейлтанк
     */
    public static function updateSubscriber($subscriber, $tagList = [], $propertyList = [])
    {
        if (!$subscriber)
            return false;

        // Ничего обновлять не надо
        if (empty($propertyList) && empty($tagList))
            return true;

        foreach ($propertyList as $key => $value)
            $subscriber->setProperty($key, $value);

        if (is_array($tagList) && !empty($tagList))
            $subscriber->tags = array_unique(array_merge($subscriber->tags, $tagList));

        return $subscriber->save();
    }

    /**
     * Удалить тег у подписчика
     */
    public static function removeSubscriberTags($subscriber, $tagList = [])
    {
        if (!$subscriber)
            return true;

        if (!is_array($tagList) || empty($tagList))
            return true;

        $subscriber->tags = array_diff($subscriber->tags, $tagList);
        return $subscriber->save();
    }

    /**
     * Проверка отписки от тега
     * Если мы когда-нить были отписаны от этого тега, возвращается true
     * @param  string $email
     * @param  string $tags
     * @return boolean true - уже отписан (проверяется отписка хотя бы от одного тега)
     */
    public static function hasUnsubscribeAction($email, $tags)
    {
        $mailtankId = self::getExternalId($email);
        if (!$mailtankId)
            return false;
        
        if (!is_array($tags))
            $tags = [$tags];

        $page = 0;
        do {
            $page++;
            $data = Yii::$app->mailtankClient->sendRequest(
                '/unsubscribed/',
                [
                    'subscriber_id' => $mailtankId
                ],
                'get'
            );

            foreach ($data['objects'] as $obj) {
                foreach ($tags as $tag) {
                    if (in_array($tag, $obj['mailing_unsubscribe_tags'])) {
                        foreach ($obj['events'] as $ev) {
                            if ($ev['type'] == 'action')
                                return true;
                        }
                    }
                }
            }
        } while ($page < $data['pages_total']);

        return false;
    }

    /**
     * Checking for correct email and return it
     * @param  string $string email address
     * @return boolean|string
     */
    public static function checkEmail($string)
    {
        // no support for named mailboxes i.e. "Bob <bob@example.com>"
        // that needs more percent encoding to be done
        if ($string == '')
            return false;
        $string = trim($string);
        $result = preg_match('/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/i', $string);
        return $result ? $string : false;
    }

    /**
     * Синхронизация подписчиков с мейлтанком
     * @param $isSync boolean false - только сравнить, true - синхронизировать
     */
    public static function syncFromMailtank($isSync = false)
    {
        $subscriberCount = 0;
        try {
            $page = 1;
            $res = self::getPage($page);
            $totalPages = $res['pages_total'];
            while (true) {
                // Обрабатываем подписчиков
                foreach ($res['objects'] as $subscriber) {
                    $subscriberCount++;

                    $mailtankId = $subscriber['id'];
                    $email = $subscriber['email'];

                    $mailtank2email = Mailtank2Email::find()
                        ->where(['email' => $email])
                        ->asArray()
                        ->one();
                    if (!$mailtank2email) {
                        // У нас нет такого email

                        $obj = Mailtank2Email::find()
                            ->where(['mailtankId' => $mailtankId])
                            ->asArray()
                            ->one();
                        if (!$obj) {
                            // Если у нас нет такого mailtankId, то это новый подписчик
                            Console::outputColored("- Subscriber <%y".$email."%n> exists only in the mailtank", false);
                            if ($isSync) {
                                if (self::createInnerSubscriber($email, $mailtankId))
                                    Console::outputColored("...%g created%n", false);
                                else
                                    Console::outputColored("...%r not created%n", false);
                            }
                            Console::output();
                        } else {
                            // Если у нас есть такой mailtankId, то прописываем ему email с мейлтанка
                            Console::outputColored("- Found inner subscriber mailtankId=<%y".$mailtankId."%n> but other email=<%y".$obj['email']."%n>", false);
                            if ($isSync) {
                                $obj['email'] = $email;
                                if ($obj->save())
                                    Console::outputColored("...%g changed to <".$email.">%n", false);
                                else
                                    Console::outputColored("...%r not changed%n", false);
                            }
                            Console::output();
                        }
                    }
                    else {
                        // У нас есть такой email

                        // Если совпадает mailtankId, то корректный пользователь и ничего делать не надо
                        if ($mailtank2email['mailtankId'] == $mailtankId)
                            continue;

                        Console::outputColored("- Same emails <%y".$email."%n> belong to different subscribers in the mailtank. Inner id=<%y".$mailtank2email['mailtankId']."%n>, mailtank id=<%y".$mailtankId."%n>");
                        if ($isSync) {
                            // Если не совпадает mailtankId, ищем подписчика по mailtankId с мейлтанка
                            $obj = Mailtank2Email::find()
                                ->where(['mailtankId' => $mailtankId])
                                ->asArray()
                                ->one();
                            if ($obj) {
                                // Если нашли, то удаляем его, а предыдущему меняем mailtankId
                                if ($obj->delete()) {
                                    Console::outputColored("  %gInner subscriber mailtankId <".$mailtankId."> succesfully delete%n");
                                } else {
                                    Console::outputColored("  %rWrong delete mailtank2email with mailtankId=<".$mailtankId.">%n");
                                    die;
                                }
                            }
                            $mailtank2email['mailtankId'] = $mailtankId;
                            if ($mailtank2email->save())
                                Console::outputColored("  %gFor email <".$email."> changed MailtankId to <".$mailtankId.">%n");
                            else
                                Console::outputColored("  %rFor email <".$email."> didn't change MailtankId to <".$mailtankId.">%n");
                        }
                    }
                }

                if ($page >= $totalPages)
                    break;
                $page++;
                $res = self::getPage($page);
            }
        } catch (MailtankException $e) {
            if ($e->getCode() == 404)
                return true;
            throw $e;
        }
    }

    /**
     * Получить очередную страницу подписчиков
     */
    private static function getPage($page)
    {
        $data = Yii::$app->mailtankClient->sendRequest(
            '/subscribers/',
            ['page' => $page],
            'get'
        );
        return $data;
    }

    /**
     * Создать внутреннюю связку email и mailtankId
     */
    private static function createInnerSubscriber($email, $mailtankId)
    {
        // Грохаем все записи с таки email, если она есть, иначе будет выдаваться не тот mailtankId и все наестся
        $id = self::getExternalId($email);
        if ($id)
            Mailtank2Email::deleteAll('email = :email', [':email'=>$email]);

        // Связываем подписчика
        $m = new Mailtank2Email();
        $m->setAttributes([
            'email' => $email,
            'mailtankId' => $mailtankId
        ]);
        return $m->save();
    }

    /**
     * Преобразование строк ошибок
     * @param  string $glue разделитель
     * @param  array $arr  массив
     * @return string
     */
    public static function implodeErrors($glue, $arr)
    {
        $retStr = "";
        foreach ($arr as $a)
            $retStr .= is_array($a) ? self::implodeErrors($glue, $a) : "," . $a;

        return $retStr;
    }

    /**
     * @param  array $arr
     * @return mixed
     */
    public static function convertToString($arr)
    {
        foreach ($arr as &$v) {
            if (is_numeric($v))
                $v .= '';
            if (is_array($v))
                $v = self::convertToString($v);
        }
        unset($v);

        return $arr;
    }

    /**
     * Преобразуем из cp1251 в utf8, чтобы некорректные символы не ложили json_encode при отправке писем
     * @param  mixed $src
     */
    public static function convertToUtf8($src)
    {
        if (is_object($src))
            return $src;

        if (is_array($src)) {
            foreach ($src as $k=>$v) {
                $src[$k] = self::convertToUtf8($v);
            }
            return $src;
        }

        $r = json_decode(@json_encode($src));
        if ($r === null) {
            $showError = false;
            try {
                $srcBase = $src;
                $src = @iconv('cp1251', "utf-8//IGNORE", $src);
                if (!$src)
                    $showError = true;
            } catch(\Exception $e) {
                $showError = true;
            }
            if ($showError) {
                Yii::error(['msg'=>'MailHelper::iconv incorrect convert', 'data'=>['baseValue'=>$srcBase, 'endValue'=>$src]]);
            }
        }
        return $src;
    }

    /**
     * Creating ID to template for mailtank
     */
    public static function createLayoutId($templateName, $prefix)
    {
        $id = preg_replace("/(\/|\.)/u", '_', str_replace("\\", "/", $prefix))
            . '_'
            . preg_replace("/(\/|\.)/u", '_', str_replace("\\", "/", $templateName));

        return $id;
    }
}