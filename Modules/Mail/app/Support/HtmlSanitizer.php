<?php

namespace Modules\Mail\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Strips script tags, event handlers, javascript: URLs, etc. from HTML that
 * originates outside the app (received email bodies) before it's ever stored
 * or rendered with {!! !!}.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if (!filled($html)) {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,div,span,b,i,u,strong,em,a[href],img[src|alt|width|height],table,tr,td,th,tbody,thead,ul,ol,li,blockquote,h1,h2,h3,h4,h5,h6,hr');
            $config->set('HTML.TargetBlank', true);
            $config->set('Cache.SerializerPath', storage_path('framework/cache/htmlpurifier'));
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
