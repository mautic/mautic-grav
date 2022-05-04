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
        $allowTracking = $this->config->get('plugins.mautic.tracking');
        $mauticBaseUrl = trim($mauticBaseUrl, " \t\n\r\0\x0B/");

        if ($allowTracking)
            $content = $this->loadTracking($mauticBaseUrl);
        $content = $this->embedForms($mauticBaseUrl, $page->getRawContent());
        $content = $this->embedContent($mauticBaseUrl, $content);

        $page->setRawContent($content);
    }

    /**
     * Load the tracking pixel
     *
     * @param  string $mauticBaseUrl
     * @param  array  $attrs to be attached as URL query
     */
    public function loadTracking($mauticBaseUrl)
    {
        if ($mauticBaseUrl) {
            $init = "
    (function(w,d,t,u,n,a,m){w['MauticTrackingObject']=n;
        w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),
        m=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)
    })(window,document,'script','{$mauticBaseUrl}/mtc.js','mt');

    mt('send', 'pageview');
            ";
            $this->grav['assets']->addInlineJs($init);
        }
    }

    /**
     * Embed forms
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
    public function embedContent($mauticBaseUrl, $content)
    {
        # Define regex for mautic shortcode
        $mauticContentRegex = '\[(\[?)(mautic)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)';

        # Match content for mautic shortcodes
        preg_match_all('/' . $mauticContentRegex . '/s', $content, $matches);

        if (count($matches[0]) === 0) {
            return $content;
        }

        foreach ($matches[0] as $key => $embed) {
            parse_str(trim(str_replace(" ", "&", $matches[3][$key])), $args);

            $type = '';
            $id = '';
            $alias = '';
            $search = trim($matches[0][$key]);
            $replace = '';
            $defaultContent = trim($matches[5][$key]);

            # Supporting legacy releases (for backwards compatibility to  1.3.2
            if (array_key_exists('slot', $args))
                $id = trim($args['slot'], '"');
            if (array_key_exists('item', $args))
                $id = trim($args['item'], '"');

            # Extract parameters from shortcode; If both legacy and current
            # parameters are given, current parameter has precedence and
            # overrides a possible legacy parameter 
            if (array_key_exists('type', $args))
                $type = trim($args['type'], '"');
            if (array_key_exists('id', $args))
                $id = trim($args['id'], '"');

            # Support backwards compatibility to 1.5.0
            if (array_key_exists('alias', $args)) {
                $alias = trim($args['alias'], '"');
                $id = $id . ':' . $alias;
            }

            # Handle forms
            if ($type == "form")
                $replace =
                    '<script type="text/javascript" src="'
                    . $mauticBaseUrl
                    . '/form/generate.js?id='
                    . $id
                    . '"></script>';

            # Handle dynamic web content (DWC)
            if ($type == "content")
                $replace =
                    '<div data-slot="dwc" data-param-slot-name="'
                    . $id
                    . '">'
                    . $defaultContent
                    . '</div>';

            # Handle focus items
            if ($type == "focus")
                    $replace =
                        '<script src="'
                        . $mauticBaseUrl
                        . "/focus/"
                        . $id
                        . '.js" type="text/javascript" charset="utf-8" async="async"></script>';

            # Handle assets
            if ($type == "asset")
                    $replace =
                        '<a href="'
                        . $mauticBaseUrl
                        . '/asset/'
                        . $id
                        . '">'
                        . $defaultContent
                        . '</a>';

            # Generate content
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }
}
