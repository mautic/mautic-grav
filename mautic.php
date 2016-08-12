<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

class MauticPlugin extends Plugin
{
    /**
     * Regex to capture all {mautic} tags in content
     *
     * @var string
     */
    private $mauticRegex = '/\[(\[?)(mautic)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/i';

    /**
     * Taken from WP get_shortcode_atts_regex
     *
     * @var string
     */
    private $attsRegex   = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';

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
        $rawContent    = $page->getRawContent();

        $this->loadTracking($mauticBaseUrl, [
            'title'     => $page->title(),
            'url'       => $this->grav['uri']->url,
            'referrer'  => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
        ]);

        // Do form replacement
        $rawContent = $this->embedForms($mauticBaseUrl, $rawContent);

        // simple performance check to determine whether bot should process further
        if (strpos($rawContent, '[mautic') === false)
        {
            return;
        }

        preg_match_all($this->mauticRegex, $rawContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            $atts = $this->parseShortcodeAtts($match[3]);
            $atts['mautic_base_url'] = $mauticBaseUrl;
            $method = 'do' . ucfirst(strtolower($atts['type'])) . 'Shortcode';
            $newContent = '';

            if (method_exists($this, $method))
            {
                $newContent = call_user_func(array($this, $method), $atts, $match[5]);
            }

            $rawContent = str_replace($match[0], $newContent, $rawContent);
        }

        $page->setRawContent($rawContent);
    }

    /**
     * Load the tracking pixel
     *
     * @param  string $mauticBaseUrl
     * @param  array  $attrs to be attached as URL query
     */
    public function loadTracking($mauticBaseUrl, $atts = [])
    {
        $jsonAtts = json_encode($atts, JSON_FORCE_OBJECT);

        if ($mauticBaseUrl) {
            $init = "
    (function(w,d,t,u,n,a,m){w['MauticTrackingObject']=n;
        w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),
        m=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)
    })(window,document,'script','{$mauticBaseUrl}/mtc.js','mt');

    mt('send', 'pageview', {$jsonAtts});
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
     * Do a find/replace for Mautic dynamic content
     *
     * @param array  $atts
     * @param string $content
     *
     * @return string
     */
    public function doContentShortcode($atts, $content)
    {
        return '<div class="mautic-slot" data-slot-name="' . $atts['slot'] . '">' . $content . '</div>';
    }

    /**
     * Do a find/replace for Mautic gated video
     *
     * @param array $atts
     *
     * @return string
     */
    public function doVideoShortcode($atts)
    {
        $video_type = '';
        $atts = $this->filterAtts(array(
            'gate-time' => 15,
            'form-id' => '',
            'src' => '',
            'width' => 640,
            'height' => 360
        ), $atts);

        if (empty($atts['src']))
        {
            return 'You must provide a video source. Add a src="URL" attribute to your shortcode. Replace URL with the source url for your video.';
        }

        if (empty($atts['form-id']))
        {
            return 'You must provide a mautic form id. Add a form-id="#" attribute to your shortcode. Replace # with the id of the form you want to use.';
        }

        if (preg_match('/^.*((youtu.be)|(youtube.com))\/((v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))?\??v?=?([^#\&\?]*).*/', $atts['src']))
        {
            $video_type = 'youtube';
        }

        if (preg_match('/^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/))?([0-9]+)/', $atts['src']))
        {
            $video_type = 'vimeo';
        }

        if (strtolower(substr($atts['src'], -3)) === 'mp4')
        {
            $video_type = 'mp4';
        }

        if (empty($video_type))
        {
            return 'Please use a supported video type. The supported types are youtube, vimeo, and MP4.';
        }

        return '<video height="' . $atts['height'] . '" width="' . $atts['width'] . '" data-form-id="' . $atts['form-id'] . '" data-gate-time="' . $atts['gate-time'] . '">' .
        '<source type="video/' . $video_type . '" src="' . $atts['src'] . '" /></video>';
    }

    /**
     * Taken from WP wp_parse_shortcode_atts
     *
     * @param $text
     *
     * @return array|string
     */
    private function parseShortcodeAtts($text)
    {
        $atts = array();
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);

        if ( preg_match_all($this->attsRegex, $text, $match, PREG_SET_ORDER) ) {
            foreach ($match as $m) {
                if (!empty($m[1]))
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif (!empty($m[3]))
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif (!empty($m[5]))
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif (isset($m[7]) && strlen($m[7]))
                    $atts[] = stripcslashes($m[7]);
                elseif (isset($m[8]))
                    $atts[] = stripcslashes($m[8]);
            }
            // Reject any unclosed HTML elements
            foreach( $atts as &$value ) {
                if ( false !== strpos( $value, '<' ) ) {
                    if ( 1 !== preg_match( '/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value ) ) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ltrim($text);
        }

        return $atts;
    }

    /**
     * Taken fro WP wp_shortcode_atts
     *
     * @param array $pairs
     * @param array $atts
     *
     * @return array
     */
    private function filterAtts(array $pairs, array $atts)
    {
        $out = array();

        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }

        return $out;
    }
}
