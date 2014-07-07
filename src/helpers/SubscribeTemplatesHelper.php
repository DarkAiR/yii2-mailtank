<?php

namespace mailtank\helpers;

use Yii;
use yii\base\ErrorException;
use yii\helpers\FileHelper;
use yii\console\Request;
use console\Console;
use mailtank\MailtankException;
use mailtank\models\MailtankLayout;


class SubscribeTemplatesHelper
{
    /**
     * Create subscribe templates for mailtank by path to templates
     * @param string $path Path or alias to directory of mailtank templates 
     * @param string $prefix Unique for project prefix for creating template name
     */
    public static function createSubscribeTemplates($path, $prefix)
    {
        // Find all template files
        $alias = Yii::getAlias($path) . '/';
        $files = FileHelper::findFiles($alias);

        // Splitting filename to parts
        $templates = [];
        foreach ($files as $f) {
            $reg = '#(?<path>'.$alias.')(?<name>.+)\.(?<ext>html|txt)$#';
            $r = preg_match($reg, $f, $matches);
            if ($r) {
                $name = $matches['name'];
                $ext = $matches['ext'];
                $templates[$name][$ext] = 1;
            }
        }

        // Check for html and txt version
        $templates = array_filter($templates, function($value) use (&$templates) {
            if (!isset($value['txt']))
                throw new MailtankException("Template '".key($templates)."' doesn't have .txt version");
            if (!isset($value['html']))
                throw new MailtankException("Template '".key($templates)."' doesn't have .html version");
            return true;
        });

        print_r($templates);
        $templates = array_diff($templates, Yii::$app->mailtank->excludeTemplates);
        print_r($templates);
        die;

        foreach ($templates as $templateName => $dummy) {
            self::createTemplate($templateName, $alias, $prefix);
        }
    }

    /**
     * Create template on mailtank
     * @param string $templateName name of template
     * @param string $basePath path to root directory of mailtank templates
     * @param string $prefix Unique for project prefix for creating template name
     */
    private function createTemplate($templateName, $basePath, $prefix)
    {
        $html = self::renderTemplate($templateName.'.html', $basePath);
        $textPlain = self::renderTemplate($templateName.'.txt', $basePath);

        // Check for template errors
        $html = self::checkTemplate($templateName.'.html', $html);
        if ($html === false)
            return;
        $textPlain = self::checkTemplate($templateName.'.txt', $textPlain);
        if ($textPlain === false)
            return;

        // Create unique mailtank ID from domain and template name
        $id = self::createLayoutId($templateName, $prefix);

        try {
            $layout = new MailtankLayout();
            $layout->setAttributes([
                'id' => $id,
            ]);
            $layout->delete();
        } catch( Exception $e ) {
            // Do nothing
        }

        $layout = new MailtankLayout();
        $attr = array(
            'id' => $id,
            'name' => $id,
            'subject_markup' => '{{subject}}',
            'markup' => $html,
            'plaintext_markup' => $textPlain,
        );
        $layout->setAttributes($attr);
        if ($layout->save()) {
            Console::output( Console::renderColorisedString("Template <%g{$templateName}%n> was create, id=".$layout->id) );
        } else {
            $err = $layout->getErrors();
            Console::output( Console::renderColorisedString("Template <%m{$templateName}%n> wasn't create") );
            Console::addIndent();
            foreach ($err as $k=>$v) {
                Console::error($k.' : '.$v);
            }
            Console::removeIndent();
        }
    }

    /**
     * Check template
     */
    private static function checkTemplate($templateName, $text)
    {
        // NOTE: Replace have to do before preg_match_all, then for matching gone new filters
        //
        // 1. Replace twig filters to the allowed mailtank filters
        $text = preg_replace('/\|raw(\s|\||\)|\})/', '|safe$1', $text);

        // 2. If anything filters didn't find, then quit
        if (!preg_match_all('/\|\w*/', $text, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE))
            return $text;

        $findError = false;
        foreach ($matches[0] as $match) {
            // Skip the allowed mailtank filters
            if (in_array($match[0], array('|safe', '|length', '|capitalize')))
                continue;

            $findError = true;
            $start = strrpos($text, "\n", $match[1]-strlen($text));
            if ($start === false) {
                $start = ($match[1] > 32)
                    ? $match[1] - 32
                    : 0;
            }
            $end = strpos($text, "\n", $match[1]);
            if ($end === false) {
                $end = (strlen($text) - $match[1] >= 32)
                    ? $match[1] + 32
                    : strlen($text)-1;
            }
            Console::warning('<'.$templateName.'> find not allowed filter: '.$match[0]);
            Console::annotation(trim(substr($text, $start, $end-$start)));
        }
        if (!$findError)
            return $text;

        return false;
    }

    /**
     * Render mail template
     */
    private static function renderTemplate($filename, $basePath)
    {
        $content = file_get_contents($basePath . $filename);

        // Находим наследование
        $res = preg_match_all('/\{%\s*extends\s*(?s)(?>\'|\")(.*?)(?>\'|\")(?-s)\s*%\}/', $content, $matchesExtends);
        if ($res === FALSE)
            throw new ErrorException('Regular expression error');

        // Если шаблон унаследован, вставляем все его блоки в родительский шаблон
        if ($res !== 0) {
            // Вырезаем все блоки
            $res = preg_match_all('/(?s)\{%\s*block\s+(\S+)\s*%\}(.*?)\{%\s*endblock\s*%\}(?-s)/', $content, $matchesBlocks);
            if ($res === FALSE)
                throw new ErrorException('Regular expression error');

            $blocks = array_combine($matchesBlocks[1], $matchesBlocks[2]);

            // Подставляем блоки в базовый шаблон
            $baseFile = $basePath . $matchesExtends[1][0];
            $baseContent = file_get_contents($baseFile);

            foreach ($blocks as $key=>$block) {
                $baseContent = preg_replace('/(?s)\{%\s*block\s+'.$key.'\s*%\}.*?\{%\s*endblock\s*%\}(?-s)/', $block, $baseContent);
            }

            $content = $baseContent;
        }

        return $content;
    }


    /**
     * Creating ID to template for mailtank
     */
    private static function createLayoutId($templateName, $prefix)
    {
        $id = preg_replace("/(\/|\.)/u", '_', str_replace("\\", "/", $prefix))
            . '_'
            . preg_replace("/(\/|\.)/u", '_', str_replace("\\", "/", $templateName));

        return $id;
    }
}