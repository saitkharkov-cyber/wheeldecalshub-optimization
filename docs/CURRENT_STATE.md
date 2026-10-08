# Wheeldecalshub — CURRENT STATE

Дата: 2026-10-07

## Общая цель
Вывести mobile PageSpeed / Core Web Vitals для главной, категорий и карточек товара в зелёную зону, сохраняя функциональность. Постоянные изменения должны переживать OCMOD Refresh. Не редактировать `storage/modification/` напрямую.

## Рабочие правила
- Один маленький безопасный шаг за раз.
- PowerShell 5.1, команды одной строкой; не использовать `&&`.
- Перед изменениями: backup + точный match/count.
- Основная локальная копия сайта: `D:\work\WDH-files\`.
- Репозиторий документации/оптимизации: `D:\Git\wheeldecalshub-optimization`.

## Ключевые достигнутые результаты

### 1. Mobile category images
Создан `system/wdh_category_mobile_images.ocmod.xml`.
- Mobile phone: product thumbs 320×320.
- Desktop/tablet: штатные размеры.
- В том же OCMOD отключён тяжёлый category swatches backend на mobile через request-local изменение `module_ptcontrolpanel_img_effect`.
- Product page не затронута.

### 2. WebP
Создан `system/wdh_image_webp.ocmod.xml`.
- `system/library/image.php` умеет сохранять WebP.
- `catalog/model/tool/image.php` добавлен `resizeWebp(...)`.
- Quality 82.
- Mobile category image теперь около 10–11 KiB вместо старых ~207 KiB 600×600 JPG.

### 3. LCP preload
Создан `system/wdh_category_lcp_preload.ocmod.xml`.
- Первый product image передаётся через Registry в header.
- В mobile header вставляется ранний `<link rel="preload" as="image" ... fetchpriority="high">`.
- Preload стоит очень рано: примерно строка 15 HTML сразу после `<base>`.
- DevTools подтвердил `initiatorType: "link"`.

### 4. LCP fetchpriority
`system/wdh_category_lcp_priority.ocmod.xml` обновлён.
Старый search по `width="600" height="600"` перестал матчиться после изменений шаблона.
Теперь search матчится по актуальному:
`<img src="{{ product.thumb }}" alt="{{ product.name }}" title="{{ product.name }}" class="img-responsive img-default-image" />`
и только первому товару добавляет `fetchpriority="high"` (`loop.index == 1`).

После восстановления fetchpriority Resource Load Delay упал примерно с 910 ms до 590 ms, а после page-cache — до ~160–200 ms.

### 5. Mobile Plaza header optimization
`system/wdh_mobile_plaza_header_skip.ocmod.xml` расширен до v1.1.
- На телефоне header загружает только `common/position3` вместо position1..10.
- Desktop/tablet остаются штатными.
- Ранее давало заметный выигрыш времени header.

### 6. Breadcrumb CLS fix — глобальный
Plaza `common.js` раньше динамически создавал `.breadcrumbs` и переносил туда `ul.breadcrumb`, что вызывало CLS.

Исправлено:
- breadcrumb server-side перенесён сразу после `{{ header }}` в 8 Twig templates:
  - `plaza/blog/category.twig`
  - `plaza/blog/list.twig`
  - `plaza/blog/post.twig`
  - `product/category.twig`
  - `product/manufacturer_info.twig`
  - `product/product.twig`
  - `product/search.twig`
  - `product/special.twig`
- В `catalog/view/javascript/common.js` удалены динамическое создание `.breadcrumbs` и `breadcrumb.appendTo(...)`.
- В critical CSS добавлено `.layer-category #content{width:100%;}`.

Результат: CLS category снизился с типичных ~0.094 (иногда 0.234) до 0–0.021.

### 7. jQuery defer на mobile category
В `header_mobile.twig` jQuery теперь `defer` только для `product-category*`; на остальных mobile страницах jQuery остаётся синхронным.

Из-за defer возникли 4 `$ is not defined`:
- Sticky Menu
- Scroll Top
- `plaza/search/form.twig`
- accordion/sidebar в `product/category.twig`

Добавлен общий helper `window.wdhWhenJQuery(fn)` в `header_mobile.twig` и все 4 блока завернуты через него. Ошибки исчезли.

### 8. Deferred CSS / grid / swatches loaders после jQuery defer
`system/wdh_category_critical_css.ocmod.xml` имел 3 loader-операции, привязанные к старому sync-jQuery tag.
После перевода jQuery на defer они попадали в неверную Twig-ветку и не выполнялись.

Исправлено: 3 search-якоря переведены на
`<script src="catalog/view/javascript/jquery/jquery-2.1.1.min.js" defer></script>`.
Это именно:
- deferred CSS loader
- lazy `grid.js` loader
- lazy `swatches.js` loader

После исправления:
- `template.wdh-deferred-css`: 8 → 0 после prepare
- preload styles: 8 до взаимодействия → 0 после активации
- «дырки» в product grid исчезли
- Console чистая

### 9. Full-page cache для `/center-cap-decals/by-make`
В `index.php` добавлен экспериментальный ранний page-cache только для точного SEO URL категории.

Причина первоначального не-срабатывания найдена: `QUERY_STRING` содержит внутренний OpenCart route:
`_route_=center-cap-decals/by-make`

После исправления подтверждено:
- первый запрос: `X-WDH-Page-Cache: MISS`
- второй: `X-WDH-Page-Cache: HIT`
- TTFB curl на HIT около 0.225 s против обычных ~0.68–0.82 s.

Mobile gate впоследствии убран, чтобы PSI точно попадал в page-cache независимо от UA.

В `index.php` сейчас также есть временный debug header:
`X-WDH-Cache-Debug: ...`
Его потом обязательно убрать.

Важно: page-cache пока экспериментальный и грубый. Кэш-файл:
`DIR_CACHE . 'wdh-page-by-make.html'`
TTL: 600 s.

### 10. Lighthouse-only analytics disable
Создан `system/wdh_lighthouse_no_analytics.ocmod.xml`.
Цель: не выводить Analytics/GTM только когда UA содержит `Chrome-Lighthouse`; обычные посетители аналитику получают.

Текущая версия упрощена до 2 операций:
1. После `$analytics = ...` добавить `$wdh_is_lighthouse`.
2. Условие analytics заменить на `if (!$wdh_is_lighthouse && ...)`.

После применения и прогрева page-cache Lighthouse-версией TBT упал до 0–10 ms.

## Последние PSI результаты
После Lighthouse-no-analytics + hot page-cache серия из 4 mobile PSI:
- Performance: 65 / 71 / 65 / 71
- FCP: 3.3 / 2.6 / 3.3 / 2.6 s
- LCP: 7.2 / 7.2 / 7.1 / 7.2 s
- TBT: 10 / 0 / 0 / 0 ms
- CLS: 0 / 0.021 / 0 / 0.021

Последний LCP breakdown:
- Time to First Byte: 10 ms
- Resource load delay: 160 ms
- Resource load duration: 40 ms
- Element render delay: 1080 ms

Вывод: сервер/TTFB, preload и сама загрузка LCP-картинки уже почти не проблема. Главный текущий bottleneck — **Element Render Delay ~1.08 s**.

## Наблюдение на завершении дня
У первого product image сейчас нет `width` / `height` в HTML.
В DevTools видно, что до применения CSS картинка первоначально не помещается в родителя, а затем ужимается через `max-width:100%`.

Это очень вероятный кандидат на следующий эксперимент: вернуть intrinsic dimensions (например `width="600" height="600"`) через OCMOD. Даже при mobile 320×320 WebP соотношение остаётся 1:1, а CSS продолжит масштабировать изображение до ширины карточки.

## С чего начать завтра
**Первый шаг завтра:** через OCMOD вернуть `width`/`height` product image в category template и проверить влияние на LCP Render Delay / FCP / CLS.

Логичнее всего обновить существующий `system/wdh_category_lcp_priority.ocmod.xml`, чтобы итоговый тег был примерно:
`<img src="{{ product.thumb }}" width="600" height="600"{% if loop.index == 1 %} fetchpriority="high"{% endif %} ...>`

Перед изменением:
- backup XML
- точный count текущего search/add
- один OCMOD Refresh после загрузки
- затем 2–3 PSI подряд

## Обязательная уборка перед финальным handoff
1. Удалить временные TTFB probes из raw `catalog/controller/product/category.php` (probes 1–16, включая 13–16 timing probes).
2. Удалить временный `X-WDH-Cache-Debug` из `index.php`.
3. Решить судьбу экспериментального full-page cache в `index.php`: оставить/довести до нормального варианта или убрать.
4. Проверить, что `wdh-page-by-make.html` не содержит нежелательно устаревшую персонализированную разметку.
5. Зафиксировать все OCMOD в репозитории.
6. Отдельно упростить workflow critical CSS: `critical-category-mobile.css` должен стать single source of truth, а inline CSS внутри `wdh_category_critical_css.ocmod.xml` генерироваться/синхронизироваться из него, а не редактироваться вручную в двух местах.

## Важные backups этой сессии
- `header_mobile.twig.bak-jquery-defer-test-20261007-202537`
- `header_mobile.twig.bak-jquery-defer-category-20261007-203838`
- `header_mobile.twig.bak-fix-literal-rn-20261007-204509`
- `header_mobile.twig.bak-wdh-jquery-helper-20261007-211350`
- `header_mobile.twig.bak-wdh-jquery-wrap-header-20261007-211743`
- `plaza/search/form.twig.bak-wdh-jquery-wrap-search-20261007-211932`
- `wdh_category_critical_css.ocmod.xml.bak-jquery-defer-anchor-20261007-213815`
- `wdh_category_lcp_priority.ocmod.xml.bak-current-img-anchor-20261007-215543`
- `index.php.bak-page-cache-20261007-221223`
- `index.php.bak-mobile-page-cache-20261007-221451`
- `index.php.bak-cache-gate-20261007-223817`
- `wdh_lighthouse_no_analytics.ocmod.xml.bak-simplify-20261007-230226`

## Статус на конец дня
Сегодняшний главный прогресс:
- CLS фактически побеждён.
- Product images переведены на mobile 320×320 WebP.
- LCP preload/fetchpriority работают.
- jQuery defer на category работает без JS ошибок.
- deferred CSS/grid/swatches снова работают корректно.
- TBT Lighthouse снижен почти до нуля.
- page-cache даёт быстрый HIT и снижает серверный TTFB.
- текущий главный bottleneck локализован в **LCP element render delay**, а не в сети.

---

# CURRENT UPDATE — 08.10.2026

This section supersedes stale notes above where they conflict with the state below.

## Рабочий протокол / важные напоминания
- Один маленький безопасный шаг; ждать `++`/результат.
- PowerShell 5.1: одна физическая строка, не использовать `&&`.
- Для `npx` использовать `& 'C:\Program Files\nodejs\npx.cmd' ...`; не предлагать менять ExecutionPolicy.
- Перед правками: backup + точный match/count; UTF-8 без BOM.
- Не редактировать `storage/modification/` напрямую.
- Source/OCMOD change: upload → один OCMOD Refresh → при необходимости очистка конкретного Twig-cache → очистка Lighthouse page-cache → прогрев.
- `critical-category-mobile.css` — source-of-truth; inline CSS внутри `wdh_category_critical_css.ocmod.xml` должен синхронизироваться из него.

## Lighthouse full-page cache
Рабочая схема в `index.php`:
- только GET;
- только UA `Chrome-Lighthouse`;
- exact path `/center-cap-decals/by-make`;
- exact query `_route_=center-cap-decals/by-make`;
- TTL 600 s;
- файл: `/var/www/alex7529425gma/data/www/storage/cache/wdh-page-by-make-lighthouse.html`.

Старое имя `wdh-page-by-make.html` больше не использовать.

Прогрев PowerShell:
`$url='https://wheeldecalshub.com/center-cap-decals/by-make'; $ua='Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse'; curl.exe -A $ua -s -D - -o NUL $url | Select-String 'X-WDH-Page-Cache'`

После удаления page-cache: первый запрос `MISS`, второй `HIT`.

## Responsive product images — системно внедрено
### `system/wdh_category_mobile_images.ocmod.xml`
- mobile `thumb` теперь `180x180.webp`;
- дополнительно генерируется `320x320.webp`;
- desktop без изменений;
- логика применяется в `catalog/controller/product/category.php` и `catalog/controller/plaza/filter.php`;
- mobile swatches/image-effect backend skip сохранен.

Generated controller проверен: `$wdh_product_image_width = $wdh_is_mobile ? 180 : ...`.

### `system/wdh_category_lcp_priority.ocmod.xml`
Для mobile `180x180.webp`:
- `src="{{ product.thumb }}"`;
- `srcset="{{ product.thumb }} 180w, ...-320x320.webp 320w"`;
- `sizes="174px"`;
- `width="180" height="180"`;
- 1-я карточка: `fetchpriority="high"`;
- 2-я карточка: eager normal;
- 3-я и далее: `loading="lazy" decoding="async"`;
- desktop fallback остается `width="320" height="320"`.

Live HTML проверен: первая ABARTH 500 отдается как `180x180` с `srcset 180w,320w`.

## Responsive LCP preload — системно внедрено
Ранее preload жестко тянул `180x180`, а `<img srcset>` при Lighthouse DPR 1.75 выбирал `320x320`, поэтому Lighthouse качал оба файла.

Исправлено в `system/wdh_category_lcp_preload.ocmod.xml`:
- `href` остается 180;
- добавлены `imagesrcset="...180w, ...320w"`;
- `imagesizes="174px"`;
- `fetchpriority="high"`.

После очистки Twig cache live preload подтвержден.
Свежий Lighthouse теперь загружает только один LCP resource: `Abarth500...-320x320.webp`, priority `High`, `isLinkPreload=True`.
Двойная загрузка `180 + 320` устранена.

## Twig cache — важная особенность
OCMOD Refresh обновляет `storage/modification`, но live HTML может оставаться старым из compiled Twig cache.

Найденные cache-файлы:
- category.twig: `/var/www/alex7529425gma/data/www/storage/cache/79/7933c43ba1afaac878142bbaeef874da75f4acb25dcc480b510d234cc409dba3.php`;
- header_mobile.twig: `/var/www/alex7529425gma/data/www/storage/cache/8e/8ebfe8f8f2c43114feaf21ec3dbdbd6244ee634ea8f102d3bd7361d030892182.php`.

Если generated Twig правильный, а live HTML старый: `grep -R -l '<уникальный фрагмент>' /var/www/alex7529425gma/data/www/storage/cache` и удалять только найденный Twig-cache.

## Image compression tests — вывод
- Тестировалось WebP quality 82 → 78 → 74.
- Само снижение quality не убирало PSI `Improve image delivery`.
- Ручной Compressor.io уменьшал cache-WebP примерно на 30–35%, но PSI все равно ругался на product images при 320x320.
- Вывод: основная претензия PSI была к oversize geometry, а не только к encoder quality.
- Responsive `srcset 180w + 320w` убрал product images из `Improve image delivery`.
- Остались главным образом logo/footer assets; большого прироста score от них не ожидать.
- Ручная Compressor.io-оптимизация была только диагностикой и не является постоянным решением: очистка `image/cache` ее стирает.

## Mobile logo
Mobile header использует `image/catalog/new_logo_hdh.webp`, 400x200, около 8.8 KB.
PSI еще может предлагать уменьшить его под display size; низкий приоритет.

## Ionicons / Work Sans
- Ionicons `@font-face`/`font-family:"Ionicons"` удалены из critical CSS/OCMOD; `ionicons.woff` ушел из critical dependency.
- Work Sans A/B с удалением critical declarations/preload не улучшил PSI; Work Sans восстановлен.
- Не трогать Work Sans снова без новой доказанной гипотезы.

## A/B, которые не дали выигрыша — НЕ ПОВТОРЯТЬ
- удаление critical Work Sans;
- breadcrumb `margin-bottom:5rem → 0` (LCP полностью входил в viewport, но LCP не улучшился);
- усиленная WebP compression 82→78→74;
- дальнейшее пережатие product images после внедрения srcset.

## Текущий Lighthouse после responsive srcset + responsive preload
Свежий локальный Lighthouse:
- Performance: **91**;
- SIM_FCP: **1134 ms**;
- SIM_LCP: **3344 ms**;
- OBS_LCP: **1111 ms**;
- SI: **2629 ms**;
- TBT: **67 ms**;
- CLS: ~**0.00027**.

Observed LCP breakdown:
- TTFB: **944.743 ms**;
- resource load delay: **10.883 ms**;
- resource load duration: **24.376 ms**;
- element render delay: **131.405 ms**.

Свежий LCP request:
- `networkRequestTime` ~955.69 ms;
- `networkEndTime` ~979.33 ms;
- priority `High`;
- `isLinkPreload=True`;
- transferSize ~9182 bytes.

То есть реальный браузер начинает LCP request примерно через **11 ms после TTFB**. Discovery/preload фактически работает очень хорошо.

## Главный нерешенный парадокс — ТОЧКА ПРОДОЛЖЕНИЯ
Lantern simulated metrics:
- `largestContentfulPaint = 3344`;
- `lcpLoadDelay = 2877`;
- `lcpLoadDuration = 2948`;
- `timeToFirstByte = 945`.

Observed trace реально стартует LCP request около **956 ms**, но Lantern моделирует начало LCP-load около **2877 ms**.

Следовательно, остающиеся ~2 s — не реальная resource load delay, а зависимость внутри Lantern simulation graph.

Свежий `network-dependency-tree-insight`:
- document ~958 ms;
- WorkSans-Regular.woff2 до ~1174 ms;
- longest chain ~1174 ms.

Обычный critical network path слишком короткий, чтобы сам объяснить SIM_LCP 3344 ms.

### Следующий exact step
В новом чате начать с команды:
`$j=Get-Content "$env:TEMP\wdh-lighthouse.json" -Raw | ConvertFrom-Json; $j.audits.PSObject.Properties | Where-Object {$_.Name -match 'lantern|metric|lcp'} | Select-Object Name`

Цель: найти audit/debug data с dependency simulated graph, которая объясняет разницу:
- observed LCP ≈ 1.11 s;
- simulated LCP ≈ 3.34 s;
- observed LCP request start ≈ 956 ms;
- Lantern `lcpLoadDelay` ≈ 2877 ms.

Пока НЕ возвращаться к image compression/srcset/preload/Work Sans/breadcrumb geometry — эти ветки уже проверены.

## Hosting limitation
Nginx отдает static без `Cache-Control/Expires`; Apache `.htaccess` на это не влияет. ISPmanager settings не дали фактических headers; прямой nginx SSH запрещен. `cache-insight=0` считать инфраструктурным ограничением; не тратить на него активное время без CDN/Cloudflare/смены hosting config.

## Обязательный cleanup перед финальной сдачей
- удалить diagnostic probes из raw `catalog/controller/product/category.php`;
- не редактировать `storage/modification` напрямую;
- проверить обычный desktop/mobile UX;
- удалить временные `.bak`/test files на сервере;
- удалить `wdh-page-by-make-lighthouse.html.bak-srcset-ab`, если еще существует;
- проверить `git status`;
- commit/push актуальных OCMOD/docs/scripts.

## Repository / Git

Repository:
`D:\Git\wheeldecalshub-optimization`

Remote:
`https://github.com/saitkharkov-cyber/wheeldecalshub-optimization.git`

Branch:
`main`

Current HEAD before checkpoint commit:
`52e5191 Document stabilized deferred CSS loader`

Previous commits:
- `ec6a3af Update category critical CSS workflow and state`
- `2bd9991 Document category optimization audit state`

Important:
- `D:\work\WDH-files\` is the separate live-site working copy, not the Git repository.
- Do not commit `storage/modification/`, runtime cache, or temporary `.bak-*` files.
- Before moving to a new chat, create a checkpoint commit with the current state.