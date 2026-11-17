<?php

namespace Modules\Mail\Support;

use HTMLPurifier;
use HTMLPurifier_AttrDef_CSS_Length;
use HTMLPurifier_AttrDef_CSS_Multiple;
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

    /**
     * Looser profile for admin-authored marketing emails: keeps inline styles and
     * layout attributes (email clients ignore <style> blocks) but still strips scripts.
     */
    public static function cleanEmail(?string $html): string
    {
        if (!filled($html)) {
            return '';
        }

        return self::emailPurifier()->purify($html);
    }

    private static ?HTMLPurifier $emailPurifier = null;

    private static function emailPurifier(): HTMLPurifier
    {
        if (self::$emailPurifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', '*[style],p[align],br,div[align],span,b,i,u,s,strong,em,font[color|size|face],a[href|title],img[src|alt|width|height],table[width|cellpadding|cellspacing|border|align|bgcolor],tr,td[width|align|valign|colspan|rowspan|bgcolor],th[width|align|valign|colspan|rowspan|bgcolor],tbody,thead,ul,ol,li,blockquote,h1,h2,h3,h4,h5,h6,hr,center');
            $config->set('HTML.TargetBlank', true);
            // "Tricky" CSS keeps display:inline-block, which email CTA buttons rely on.
            $config->set('CSS.AllowTricky', true);
            $config->set('Cache.SerializerPath', storage_path('framework/cache/htmlpurifier'));

            // HTMLPurifier only knows CSS 2.1; allow border-radius for rounded buttons and boxes.
            // (CSS definitions can't be customised "raw", so patch the built instance this config hands out.)
            $config->getCSSDefinition()->info['border-radius'] =new HTMLPurifier_AttrDef_CSS_Multiple(new HTMLPurifier_AttrDef_CSS_Length('0'), 4);

            self::$emailPurifier = new HTMLPurifier($config);
        }

        return self::$emailPurifier;
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
