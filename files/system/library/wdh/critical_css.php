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

    public static function buildMobile($route) {
        if ($route !== 'product/category' && $route !== 'product/product') {
            return '';
        }

        $base = DIR_APPLICATION . 'view/theme/tt_uren1/stylesheet/wdh/';
        $common = $base . 'critical-common-mobile.css';

        $cacheDir = DIR_STORAGE . 'cache/wdh_critical_css/';

        if ($route === 'product/category') {
            $specific = $base . 'critical-category-mobile.css';
            $cacheName = 'category-mobile.css';
        } else {
            $specific = $base . 'critical-product-mobile.css';
            $cacheName = 'product-mobile.css';
        }

        $cacheFile = $cacheDir . $cacheName;
        if (!is_file($common)) {
            $css = is_file($cacheFile) ? self::read($cacheFile) : '';
            return $css === '' ? '' : '<style id="wdh-critical-mobile" media="(max-width:991px)">' . "\n" . $css . "\n" . '</style>';
        }

        $sources = array($common);
        if (is_file($specific)) {
            $sources[] = $specific;
        }

        $css = self::getCachedCss($cacheName, $sources);

        if ($css === '') {
            return '';
        }

        return '<style id="wdh-critical-mobile" media="(max-width:991px)">' . "\n" . $css . "\n" . '</style>';
    }
}
