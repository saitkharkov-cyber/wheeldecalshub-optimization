# Эксперимент с full-page cache для Lighthouse

Дата: 2026-10-09

## Зачем вводился кэш

Временный full-page HTML-кэш был добавлен для категории `/center-cap-decals/by-make` только для запросов, в User-Agent которых присутствует `Chrome-Lighthouse`.

Целью было уменьшить TTFB в Lighthouse/PageSpeed Insights во время оптимизации mobile category.

Файл кэша:

`wdh-page-by-make-lighthouse.html`

Механизм находился непосредственно в `index.php` и срабатывал только для точного GET-запроса этой категории и Lighthouse User-Agent.

## Результаты измерений

С прогретым full-page cache:

- TTFB: примерно 0.25 s
- размер HTML: 244837 bytes

С отключённым full-page cache:

- TTFB прямого запроса с Chrome-Lighthouse UA: примерно 0.59 s
- размер HTML остался 244837 bytes
- отключение GTM/GA продолжило работать независимо от full-page cache: `GTM=0`, `GA=0`

### Mobile PSI без full-page cache

Три последовательных прогона:

- Performance: 98
- FCP: 0.9–1.4 s
- LCP: 2.4 s
- TBT: 0 ms
- CLS: 0.03
- Speed Index: 1.7–1.9 s

### Desktop PSI без full-page cache

Три последовательных прогона:

- Performance: 97 / 97 / 100
- FCP: 0.8 / 0.8 / 0.4 s
- LCP: 1.1 / 1.1 / 0.6 s
- TBT: 0 ms
- CLS: 0.001 / 0.001 / 0
- Speed Index: 1.2 / 1.2 / 0.6 s

## Почему от full-page cache отказались

Такой кэш требует отдельного механизма инвалидирования при изменениях товаров, цен, меню, модулей, Twig, OCMOD и другого содержимого страницы.

Если применять тот же подход к карточкам товаров и главной странице, пришлось бы создавать отдельные кэши и, возможно, отдельные mobile/desktop варианты.

Измерения показали, что эта архитектурная сложность не нужна: и mobile, и desktop PSI остаются в зелёной зоне без full-page cache.

Отдельный `wdh_ptmenu_cache.ocmod.xml` к этому эксперименту не относится. Это независимый фрагментный кэш меню, его оставляем.

## Решение

Удалить Lighthouse-specific full-page HTML cache из `index.php`.

Не использовать такой подход для category, product и homepage.

## Снимок текущей отключённой реализации

Ниже сохранено промежуточное состояние `index.php`, которое было успешно протестировано: full-page cache отключён через `false &&`, но сам код ещё присутствует.

> Важно: перед окончательным удалением механизма рекомендуется вставить сюда точный актуальный блок из `index.php`, если нужен полноценный исторический снимок кода.

```php
$wdh_is_lighthouse = isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'Chrome-Lighthouse') !== false;

if (
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        false &&
        $wdh_page_cache_path === '/center-cap-decals/by-make' &&
        $wdh_page_cache_query === '_route_=center-cap-decals/by-make'
) {
        $wdh_page_cache_dir = DIR_CACHE;

        if (is_dir($wdh_page_cache_dir)) {
                $wdh_page_cache_file = $wdh_page_cache_dir . 'wdh-page-by-make-lighthouse.html';

                if (is_file($wdh_page_cache_file) && (time() - filemtime($wdh_page_cache_file) < $wdh_page_cache_ttl)) {
                        header('Content-Type: text/html; charset=utf-8');
                        header('X-WDH-Page-Cache: HIT');
                        readfile($wdh_page_cache_file);
                        exit;
                }

                ob_start();
        }
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');

if ($wdh_page_cache_file !== '' && ob_get_level()) {
        $wdh_page_cache_html = ob_get_clean();

        if ($wdh_page_cache_html !== '') {
                file_put_contents($wdh_page_cache_file, $wdh_page_cache_html, LOCK_EX);
        }

        header('X-WDH-Page-Cache: MISS');
        echo $wdh_page_cache_html;
}
```

## Как восстановить механизм

Если когда-нибудь понадобится вернуть full-page cache:

1. Вернуть сохранённый блок в `index.php`.
2. Заменить `false &&` обратно на `$wdh_is_lighthouse &&`.
3. Убедиться, что каталог `DIR_CACHE` доступен для записи.
4. Выполнить команду прогрева два раза и проверить сначала `MISS`, затем `HIT`.

## Прогрев и проверка HIT / MISS

PowerShell 5.1:

```powershell
$url='https://wheeldecalshub.com/center-cap-decals/by-make'; $ua='Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse'; curl.exe -A $ua -s -D - -o NUL $url | Select-String 'X-WDH-Page-Cache'
```

Ожидаемое поведение при включённом кэше:

- первый запрос после удаления или истечения TTL: `X-WDH-Page-Cache: MISS`
- второй запрос в пределах TTL: `X-WDH-Page-Cache: HIT`

Команду нужно запустить два раза подряд.

## Проверка TTFB / total / размера ответа под Lighthouse UA

PowerShell 5.1:

```powershell
$ua='Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse'; curl.exe -A $ua -o NUL -s -w "ttfb=%{time_starttransfer}s total=%{time_total}s size=%{size_download}`n" 'https://wheeldecalshub.com/center-cap-decals/by-make'
```

Эта команда полезна для быстрого сравнения времени ответа и размера HTML под Lighthouse User-Agent.
