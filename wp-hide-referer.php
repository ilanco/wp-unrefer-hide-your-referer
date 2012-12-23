<?php
/**
 * Plugin Name: WordPress Hide Referer
 * Plugin URI: https://github.com/ilanco/wp-unrefer-hide-your-referer
 * Description: Hide your referer.
 * Version: %VERSION%
 * Author: Ilan Cohen <ilanco@gmail.com>
 * Author URI: https://github.com/ilanco
 * License: WTFPL (http://sam.zoy.org/wtfpl/)
 */

/**
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */ 

if (defined('WP_DEBUG') && (WP_DEBUG == true)) {
  error_reporting(E_ALL);
}

// don't load directly
if (!defined('ABSPATH'))
  die(false);

add_action('init', array('WPHideReferer', 'init'), 9);

class WPHideReferer
{
    private static $instance;

    private function __construct()
    {
        // filter content text
        add_filter('the_content', array($this, 'convert'), 999);

        // filter comment text
        add_filter('comment_text', array($this, 'convert'), 999);
    }

    public static function init()
    {
        if (!self::$instance) {
            self::$instance = new WPHideReferer();
        }

        return self::$instance;
    }

    public function convert($content)
    {
        // check for links in content
        if (stripos($content, 'href=') === false) {
            return $content;
        }

        $urls = $this->domDocumentExtract($content);

        $urls = $this->regexExtract($content);

        // remove duplicate urls
        $urls = array_unique($urls);

        // check if urls are valid to unrefer
        $urls = array_filter($urls, array($this, 'validate'));

        $converted_urls = array_map(array($this, 'prefix'), $urls);

        $content = str_ireplace($urls, $converted_urls, $content);

        return $content;
    }

    private function validate($url)
    {
        return (stripos($url, 'http') === 0);
    }

    private function prefix($url)
    {
        return 'http://unrefer.com/?' . $url;
    }

    private function domDocumentExtract($content)
    {
        $urls = array();

        $lui_errors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->strictErrorChecking = false;
        $dom->loadHTML($content);


        $xPath = new DOMXPath($dom);
        $nodes = $xPath->query('//a/@href');

        foreach ($nodes as $href) {
            $urls[] = $href->nodeValue;
        }

        unset($nodes);
        unset($xPath);
        unset($dom);

        libxml_clear_errors();
        libxml_use_internal_errors($lui_errors);

        return $urls;
    }

    private function regexExtract($content)
    {
        $urls = array();

        $regex = '/(<a\s*'; // Start of anchor tag
        $regex .= '(.*?)\s*'; // Any attributes or spaces that may or may not exist
        $regex .= 'href=[\'"]+?\s*(?P<link>\S+)\s*[\'"]+?'; // Grab the link
        $regex .= '\s*(.*?)\s*>\s*'; // Any attributes or spaces that may or may not exist before closing tag
        $regex .= '(?P<name>[^<]*)'; // Grab the name
        $regex .= '\s*<\/a>)/i'; // Any number of spaces between the closing anchor tag (case insensitive)
   
        if (preg_match_all($regex, $content, $matches) !== false) {
            $urls = $matches['link'];
        }

        return $urls;
    }
}

