<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

class MauticPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPageContentRaw' => ['onPageContentRaw', 0]
        ];
    }

    /**
     * Add content after page content was read into the system.
     *
     * @param  Event  $event An event object, when `onPageContentRaw` is fired.
     */
    public function onPageContentRaw(Event $event)
    {
        if ($this->isAdmin()) {
            return;
        }

        /** @var Page $page */
        $page = $event['page'];
        $mauticBaseUrl = $this->config->get('plugins.mautic.url');
        $mauticBaseUrl = trim($mauticBaseUrl, " \t\n\r\0\x0B/");

        $this->loadTracking($mauticBaseUrl, [
            'title'     => $page->title(),
            'url'       => $this->grav['uri']->url,
            'referrer'  => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        ]);

        $content = $this->embedForms($mauticBaseUrl, $page->getRawContent());
        $content = $this->embedDynamicContent($mauticBaseUrl, $content);

        $page->setRawContent($content);
    }

    /**
     * Load the tracking pixel
     *
     * @param  string $mauticBaseUrl
     * @param  array  $attrs to be attached as URL query
     */
    public function loadTracking($mauticBaseUrl, $attrs)
    {
        if ($mauticBaseUrl) {
            $encodedAttrs = urlencode(base64_encode(serialize($attrs)));
            $init = "
                var img = new Image();
                img.src = '{$mauticBaseUrl}/mtracking.gif?d={$encodedAttrs}';
                img.alt = 'mautic is open source marketing automation';
            ";
            $this->grav['assets']->addInlineJs($init);
        }
    }

    /**
     * Load the tracking pixel
     *
     * @param  string $mauticBaseUrl
     * @param  array  $raw to be attached as URL query
     */
    public function embedForms($mauticBaseUrl, $raw)
    {
        $function = function ($matches) use ($mauticBaseUrl) {
            $search = $matches[0];

            if (!isset($matches[1])) {
                return $search;
            }

            $replace = '<script type="text/javascript" src="' . $mauticBaseUrl . '/form/generate.js?id=' . $matches[1] . '"></script>';

            return str_replace($search, $replace, $search);
        };

        return $this->parseLinks($raw, $function);
    }

    /**
     * Embed dynamic content
     *
     * @param  string $mauticBaseUrl
     * @param  string $content The content
     *
     * @return string
     */
    public function embedDynamicContent($mauticBaseUrl, $content)
    {
        $mauticContentRegex = '\[(\[?)(mauticcontent)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)';

        preg_match_all('/' . $mauticContentRegex . '/s', $content, $matches);

        if (count($matches[0]) === 0) {
            return $content;
        }

        foreach ($matches[0] as $key => $embed) {
            parse_str(trim($matches[3][$key]), $args);

            $search = trim($matches[0][$key]);
            $slot = trim($args['slot'], '"');
            $defaultContent = trim($matches[5][$key]);

            $replace  = '<script type="text/javascript" src="' . $mauticBaseUrl . '/dwc/generate.js?slot=' . $slot .'"></script>';
            $replace .= '<div id="mautic-slot-' . $slot . '">' . $defaultContent . '</div>';

            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }
}
