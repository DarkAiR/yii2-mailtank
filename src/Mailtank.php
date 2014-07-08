<?php

namespace mailtank;

use Yii;
use yii\base\InvalidConfigException;
use mailtank\MailtankException;
use mailtank\helpers\SubscribeTemplatesHelper;
use mailtank\helpers\MailtankHelper;
use mailtank\models\MailtankMailing;

class Mailtank extends \yii\base\Object
{
    public $host = '';
    public $token = '';
    public $templatesPath = '';
    public $templatePrefix = '';
    public $excludeTemplates = [];

    public function init()
    {
        parent::init();
        
        if (empty($this->host) || empty($this->token))
            throw new InvalidConfigException("Parameters 'host' and 'token', couldn't be an empty");
        
        if (empty($this->templatesPath))
            throw new InvalidConfigException("You have to fill parameters 'templatesPath' for mail templates");

        if (empty($this->templatePrefix))
            throw new InvalidConfigException("Parameter <TemplatePrefix> didn't set for mailtank templates");

        Yii::$app->components = [
            'mailtankClient' => [
                'class' => 'mailtank\MailtankClient', 
                'host' => $this->host,
                'token' => $this->token
            ]
        ];
    }

    /**
     * Creating subscribe templates
     */
    public function createSubscribeTemplates()
    {
        SubscribeTemplatesHelper::createSubscribeTemplates($this->templatesPath, $this->templatePrefix);
    }

    /**
     * Subscribe via mailtank
     * @param string  $template
     * @param string  $subject
     * @param array   $fields
     * @param array   $tags
     * @param array   $subscribers
     * @param integer $priority
     * @param boolean $tagsAndReciversUnion
     * @param array   $attachments
     * @return array|bool|string
     */
    public function sendSubscribeToMailtank(
        $template,
        $subject,
        $fields,
        $tags,
        $subscribers = [],
        $priority = null,
        $tagsAndReciversUnion = false,
        $attachments = [])
    {
        if (empty($template) || !is_string($template))
            return 'Incorrect template';

        if (!is_array($fields))
            return 'Fields is not array';

        // Если tags не массив
        if (empty($tags) || !is_array($tags))
            return 'Incorrect tags. Must be not empty array';

        // Если subscribers не массив
        if (!is_array($subscribers))
            return 'Subscribers is not array';

        // Приложенные файлы должны быть массивом
        if (!is_array($attachments))
            return 'Attachments is not array';

        $unsubscribeTags = $tags;

        // Заполняем необходимые для поля для письма
        $fields = array_merge($fields, ['subject'=>$subject]);

        // Для мейлтанка убираем ключи у подписчиков
        $subscribers = array_values($subscribers);

        // Убираем из списка подписчиков тех, кто отписался,
        // потому что мейлтанк не умеет рассылать на несуществующие теги
        $tmpSubscribers = [];
        foreach ($subscribers as $email) {
            // Почта должна быть валидной
            if (!MailtankHelper::checkEmail($email)) {
                // Отправляем в сентри
                Yii::error(['msg'=>'Невалидный email для отправки в мейлтанк', 'data'=>['email'=>$email]]);
                continue;
            }

            $s = MailtankHelper::getSubscriber($email);
            if (!$s) {
                // Создаем нового подписчика
                $userId = 0;
                $userName = '';
                $s = MailtankHelper::createSubscriber($email, $userId, $userName);
                if (!$s)
                    return 'Subscriber was not created';
            }

            // Пропускаем тех, кто не активен
            if (!$s->does_email_exist)
                continue;

            // Подписываем только на те теги, от которых пользователь не отписан
            $amountTags = 0;
            $updateTags = [];
            foreach ($tags as $t) {
                // Если тег есть, то все ок
                if (in_array($t, $s->tags)) {
                    $amountTags++;
                }
                else {
                    // Если тега нет, то проверяем были ли когда-нибудь от него отписаны
                    if (!MailtankHelper::hasUnsubscribeAction($email, $t)) {
                        $updateTags[] = $t;
                        $amountTags++;
                    }
                }
            }
            if (count($updateTags) > 0) {
                if (!MailtankHelper::updateSubscriber($s, $updateTags))
                    return 'Error during update tags for subscriber';
            }

            if ($amountTags > 0) {
                // Хотя бы один тег есть
                $tmpSubscribers[] = $s->id;
            }
        }

        // Если все подписчики отсеялись, то не надо рассылать, иначе рассылка уйдет всем
        if (count($subscribers) > 0 && count($tmpSubscribers) == 0)
            return true;

        return $this->sendToMailtank($template, $fields, $tags, $unsubscribeTags, $tmpSubscribers, $priority, $tagsAndReciversUnion, $attachments);
    }

    /**
     * Отправка одиночного письма через мейлтанк
     * Если надо разослать нескольких подписчикам, то см. sendSubscribeToMailtank
     * @param string    $email      почта получателя
     * @param string    $template   шаблон письма
     * @param string    $subject    тема письма
     * @param array     $fields     данные, передаваемые в шаблон
     * @param array     $tags       теги подписки/отписки
     * @param integer   $userId     Id получателя, если есть (запишется в подписчика, не обязательное)
     * @param string    $userName   Имя получателя, если есть (запишется в подписчика, не обязательное)
     * @param array     $attachments
     * @return true|string Возвращается или true или строка ошибки
     */
    public function sendSingleMailToMailtank(
        $email,
        $template,
        $subject,
        $fields,
        $tags,
        $userId = 0,
        $userName = '',
        $attachments = [])
    {
        $tagsAndReciversUnion = false;
        $priority = null;

        // Если вообще не пришли параметры
        if (empty($email) || !is_string($email))
            return 'No email';

        // Почта должна быть валидной
        $email = MailtankHelper::checkEmail($email);
        if (!$email)
            return 'Incorrect email';

        if (empty($template) || !is_string($template))
            return 'Incorrect template';

        if (!is_array($fields))
            return 'Fields is not array';

        // Если tags не массив
        if (empty($tags) || !is_array($tags))
            return 'Incorrect tags. Must be not empty array';

        // Приложенные файлы должны быть массивом
        if (!is_array($attachments))
            return 'Attachments is not array';

        $unsubscribeTags = $tags;

        // Заполняем необходимые поля для письма
        $fields = array_merge($fields, ['subject'=>$subject]);

        $s = MailtankHelper::getSubscriber($email);
        if ($s) {
            $res = MailtankHelper::updateSubscriber($s, [], ['userId'=>$userId, 'userName'=>$userName]);
            if (!$res)
                return 'Error during update subscriber <'.$s->id.'>';
        } else {
            // Создаем нового подписчика
            $s = MailtankHelper::createSubscriber($email, $userId, $userName);
            if (!$s)
                return 'Subscriber <'.$s->id.'> was not created';
        }

        $mailtankId = $s->id;

        // Подписываем только на те теги, от которых пользователь не отписан
        $amountTags = 0;
        $updateTags = [];
        foreach ($tags as $t) {
            // Если тег есть, то все ок
            if (in_array($t, $s->tags)) {
                $amountTags++;
            }
            else {
                // Если тега нет, то проверяем были ли когда-нибудь от него отписаны
                if (!MailtankHelper::hasUnsubscribeAction($email, $t)) {
                    $updateTags[] = $t;
                    $amountTags++;
                }
            }
        }
        if (count($updateTags) > 0) {
            if (!MailtankHelper::updateSubscriber($s, $updateTags))
                return 'Error during update tags for subscriber';
        }

        if ($amountTags > 0) {
            // Хотя бы один тег есть, рассылаем по тегу
            if (!$this->sendToMailtank($template, $fields, $tags, $unsubscribeTags, [$mailtankId], $priority, $tagsAndReciversUnion, $attachments))
                return 'Error during sending mail. See sentry for details.';
        }
        return true;
    }

    /**
     * Отправить рассылку в мейлтанк
     */
    private function sendToMailtank(
        $template,
        $fields,
        $tags = [],
        $unsubscribeTags = [],
        $subscribers = [],
        $priority = null,
        $tagsAndReciversUnion = false,
        $attachments = [])
    {
        // NOTE: Т.к. метод приватный и все необходимые проверки сделаны,
        //       предполагаем, что все параметры правильные

        $fields = MailtankHelper::convertToString($fields);

        $attributes = [
            'layout_id' => MailtankHelper::createLayoutId($template, $this->templatePrefix),
            'context' => $fields
        ];
        if (is_array($attachments) && !empty($attachments)) {
            $attributes['attachments'] = $attachments;
        }
        if (is_array($tags) && !empty($tags)) {
            $tags = array_values($tags);
            $attributes['tags'] = $tags;
        }
        if (is_array($subscribers) && !empty($subscribers)) {
            $subscribers = array_values($subscribers);
            $attributes['subscribers'] = $subscribers;
        }

        $mailing = new MailtankMailing();
        $mailing->setAttributes($attributes);

        if (is_array($unsubscribeTags) && !empty($unsubscribeTags))
            $mailing->unsubscribe_tags = $unsubscribeTags;

        $mailing->tags_and_receivers_union = $tagsAndReciversUnion;

        try {
            $mailing->save();
            switch ($mailing->status) {
                case 'SUCCEEDED':
                case 'ENQUEUED':
                    return true;
            }
            throw new MailtankException(MailtankHelper::implodeErrors('; ', $mailing->getErrors()));
        }
        catch (MailtankException $e) {
            // NOTE: Данный код нужен только для того, чтобы отслеживать недоступность сервиса
            //       При реальной ошибке мы ее не обнаружим, т.к. на этом уровне
            //       нельзя различить недоступность сервиса и ошибку мейлтанка, поэтому Sentry нам в помощь

            // Отправляем в сентри
            Yii::error([
                'msg' => 'Ошибка отправки сообщения в mailtank',
                'data' => array_merge(['exceptionMsg'=>$e->getMessage()], $mailing->getErrors())
            ]);
            throw $e;
        }
    }
}
