<?php
class WdhCriticalCss {
    public static function read($file) {
        return is_file($file) ? file_get_contents($file) : '';
    }

    private static function getCachedCss($cacheName, $sources) {
        $cacheDir = DIR_STORAGE . 'cache/wdh_critical_css/';
        $cacheFile = $cacheDir . $cacheName;
        $cacheExists = is_file($cacheFile);
        $rebuild = !$cacheExists;

        if ($cacheExists) {
            $cacheTime = filemtime($cacheFile);

            foreach ($sources as $source) {
                if (is_file($source) && filemtime($source) > $cacheTime) {
                    $rebuild = true;
                    break;
                }
            }
        }

        if (!$rebuild) {
            return self::read($cacheFile);
        }

        $parts = array();
        foreach ($sources as $source) {
            if (is_file($source)) {
                $content = self::read($source);
                if ($content !== '') {
                    $parts[] = $content;
                }
            } elseif ($cacheExists) {
                return self::read($cacheFile);
            }
        }

        if (!$parts) {
            return $cacheExists ? self::read($cacheFile) : '';
        }

        $css = implode("\n", $parts);

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return $cacheExists ? self::read($cacheFile) : $css;
        }

        $tmpFile = $cacheFile . '.tmp.' . getmypid();
        if (file_put_contents($tmpFile, $css, LOCK_EX) !== false) {
            if (!@rename($tmpFile, $cacheFile)) {
                @unlink($tmpFile);
            }
        }

        return $css;
    }

    public static function buildDesktop($route) {
        if ($route !== 'product/category') {
            return '';
        }

        $base = DIR_APPLICATION . 'view/theme/tt_uren1/stylesheet/wdh/';
        $css = self::getCachedCss('category-desktop.css', array($base . 'critical-desktop.css'));

        if ($css === '') {
            return '';
        }

        return '<style id="wdh-critical-desktop" media="(min-width:992px)">' . "\n" . $css . "\n" . '</style>';
    }
    public static function buildMobile($route) {
        if ($route !== 'product/category' && $route !== 'product/product' && $route !== 'common/home') {
            return '';
        }

        $base = DIR_APPLICATION . 'view/theme/tt_uren1/stylesheet/wdh/';
        $common = $base . 'critical-common-mobile.css';

        $cacheDir = DIR_STORAGE . 'cache/wdh_critical_css/';

        if ($route === 'product/category') {
            $specific = $base . 'critical-category-mobile.css';
            $cacheName = 'category-mobile.css';
        } elseif ($route === 'common/home') {
            $specific = $base . 'critical-home-mobile.css';
            $cacheName = 'home-mobile.css';
        } else {
            $specific = $base . 'critical-product-mobile.css';
            $cacheName = 'product-mobile.css';
        }

        $cacheFile = $cacheDir . $cacheName;
        if (!is_file($common)) {
            $css = is_file($cacheFile) ? self::read($cacheFile) : '';
            return $css === '' ? '' : '<style id="wdh-critical-mobile" media="(max-width:991px)">' . "\n" . $css . "\n" . '</style>';
        }

        $sources = array();
        if ($route === 'product/category' || $route === 'common/home') {
            $bestin = $base . 'bestin.css';
            if (is_file($bestin)) {
                $sources[] = $bestin;
            }
        }
        $sources[] = $common;
        if (is_file($specific)) {
            $sources[] = $specific;
        }

        $css = self::getCachedCss($cacheName, $sources);

        if ($css === '') {
            return '';
        }

        return '<style id="wdh-critical-mobile" media="(max-width:991px)">' . "\n" . $css . "\n" . '</style>';
    }

    public static function deferredCssLoaderJs() {
        return <<<'JS'
(function(){var ready=false,activated=false,links=[];var events=['pointerdown','touchstart','keydown','wheel'];function activate(){if(!ready||activated)return;activated=true;links.forEach(function(x){x.link.setAttribute('rel',x.rel);if(x.media===null){x.link.removeAttribute('media');}else{x.link.setAttribute('media',x.media);}if(x.as===null){x.link.removeAttribute('as');}else{x.link.setAttribute('as',x.as);}});events.forEach(function(ev){window.removeEventListener(ev,activate,false);});}function prepare(){var nodes=[].slice.call(document.querySelectorAll('template.wdh-deferred-css'));if(!nodes.length){ready=true;return;}var left=nodes.length;function done(){left--;if(left===0)ready=true;}nodes.forEach(function(t){var src=t.content.querySelector('link');var l=document.createElement('link');Array.prototype.forEach.call(src.attributes,function(a){l.setAttribute(a.name,a.value);});var rel=l.getAttribute('rel')||'stylesheet',media=l.getAttribute('media'),as=l.getAttribute('as');l.setAttribute('rel','preload');l.setAttribute('as','style');l.onload=done;l.onerror=done;t.parentNode.replaceChild(l,t);links.push({link:l,rel:rel,media:media,as:as});});}events.forEach(function(ev){window.addEventListener(ev,activate,{passive:true});});window.addEventListener('load',prepare,{once:true});})();
JS;
    }}
