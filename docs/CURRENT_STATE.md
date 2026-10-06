# CURRENT STATE

Дата: **06.10.2026**

## Проект

Сайт: https://wheeldecalshub.com/  
CMS: OpenCart 3.0.3.2  
PHP: 7.3.33  
Тема: `tt_uren1`

Локальный репозиторий документации/скриптов: `D:\Git\wheeldecalshub-optimization`  
Полная локальная копия сайта: `D:\work\WDH-files\`

Важно: репозиторий используется прежде всего для документации и вспомогательных скриптов. Боевые файлы сайта/OCMOD находятся в `D:\work\WDH-files\` и загружаются на hosting отдельно.

## Рабочий протокол

- Один маленький безопасный шаг за раз.
- Перед правкой существующего файла: backup + exact match/count.
- PowerShell 5.1: команды давать одной строкой; не использовать `&&`.
- UTF-8 без BOM.
- Не редактировать `storage/modification/` напрямую.
- Постоянные изменения должны переживать OCMOD Refresh.
- Цикл: local source → upload → один OCMOD Refresh → hard reload/functionality → PSI.
- Hosting иногда дает transient 502/temporary overload при OCMOD Refresh; один 502 не означает ошибку OCMOD. Не спамить Refresh.
- Desktop/tablet сейчас не трогать при mobile-category оптимизации.

## Baseline Mobile PageSpeed / Lighthouse

| Тип страницы | Performance | FCP | LCP | TBT | CLS | SI |
|---|---:|---:|---:|---:|---:|---:|
| Главная | 44 | 6.8 s | 8.8 s | 0 ms | 0.285 | 6.8 s |
| Каталог | 35 | 7.4 s | 12.9 s | 0 ms | 0.704 | 7.4 s |
| Карточка товара | 45 | 8.5 s | 9.6 s | 200 ms | 0.194 | 8.5 s |

Baseline screenshots: `measurements/baseline/`.

## Текущий фокус

Текущая рабочая точка — **mobile category**.

Основная задача на следующую сессию: **LCP / product image delivery**, а не дальнейший широкий JS-defer.

Причина: JavaScript blocking уже значительно снижен, TBT в последних тестах в основном зеленый, а Performance продолжает сильно зависеть от LCP первого product image.

---

# 1. Категория — CLS и initial grid

Основная исходная причина высокого CLS была подтверждена: товары сервером приходили как list, после чего `grid.js` перестраивал их в grid.

После server-side initial grid + резервирования dimensions CLS был снижен примерно с **0.704** до стабильных **~0.094–0.095**. В отдельных PSI-run CLS был **0**.

Текущие классы карточки:

`product-layout product-grid grid-style col-lg-3 col-md-3 col-sm-3 col-xs-6 product-items`

Product thumbs имеют HTML dimensions:

`width="600" height="600"`

Payment image:

`width="350" height="30"`

---

# 2. LCP image / priority

Текущий LCP mobile category — первая product image.

Через:

`system/wdh_category_lcp_priority.ocmod.xml`

первой product image добавляется:

`fetchpriority="high"`

Lighthouse больше не показывает прежнее предупреждение late LCP discovery как стабильную проблему.

Resource load delay заметно плавает между PSI-run, поэтому одиночный плохой запуск не считать доказательством постоянной late-discovery проблемы.

---

# 3. Critical CSS — single source of truth

Ручной source-файл:

`D:\work\WDH-files\catalog\view\theme\tt_uren1\stylesheet\critical-category-mobile.css`

Боевой OCMOD:

`D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml`

Текущая версия OCMOD:

`1.2-test-critical-inline`

Critical CSS вставляется inline в `<head>` mobile category.

Обычные Bootstrap/theme/icon CSS не применяются на initial render; их original links сохраняются как deferred templates.

### Sync workflow

Репозиторий содержит helper:

`scripts/sync-critical-css.ps1`

Запуск:

`powershell.exe -NoProfile -ExecutionPolicy Bypass -File 'D:\Git\wheeldecalshub-optimization\scripts\sync-critical-css.ps1'`

Скрипт:
- требует ровно один `<style>` block в OCMOD;
- делает timestamped backup OCMOD;
- заменяет inline critical CSS содержимым source-файла;
- пишет UTF-8 без BOM.

Правило: **редактировать critical CSS только в source-файле, затем sync**. Не дублировать ручные изменения непосредственно внутри OCMOD.

---

# 4. Стабильная deferred CSS architecture

Предыдущий sequential delayed CSS loader признан неудачным и не должен возвращаться.

Он вызывал staged repaint и CLS около **0.614**.

Текущая стабильная схема:

1. Initial render использует critical CSS.
2. Восемь suppressed original CSS links сохраняются в исходном порядке как `<template class="wdh-deferred-css">`.
3. После `window.load` все deferred CSS скачиваются **параллельно** как `rel="preload" as="style"`, но не применяются.
4. После того как весь batch готов, следующий `pointerdown/touchstart/keydown/wheel` синхронно возвращает links их original `rel/media/as`.
5. Если interaction произошел до готовности batch, CSS не применяется автоматически позднее; активация ждет следующего interaction.

Эта схема выбрана специально, чтобы крупные post-interaction layout shifts оставались внутри `hadRecentInput=true` и не засчитывались как CLS.

В стабильном тесте единственный `hadRecentInput=false` shift после этого был около **0.00229**.

DevTools warnings вида "resource was preloaded but not used within a few seconds" для этих CSS ожидаемы и сами по себе не являются ошибкой.

**Не менять этот loader без конкретной воспроизводимой регрессии.**

---

# 5. Critical visual state

Critical-only first screen визуально дополирован и пригоден как рабочий baseline.

В critical восстановлены/зафиксированы:
- Bootstrap-used foundation;
- header;
- breadcrumbs;
- accordion / Select Your Brand;
- mobile toolbar;
- product cards;
- local typography;
- critical icons через inline SVG masks;
- filter/sidebar controls.

### Critical icons

Критические icon fonts для first screen заменены SVG masks, в том числе:
- hamburger;
- search;
- settings;
- cart;
- home;
- filter;
- brand arrow.

SVG встроены в CSS как data URI, внешних requests к critical-icons нет.

### Header

Mobile header визуально подтвержден как стабильный.

Logo имеет explicit dimensions:

`width="1000" height="500"`

Preload включен для:
- `WorkSans-SemiBold.woff2`;
- `bestin.ttf`.

---

# 6. Bestin — финальный fix font flash

При первой стабильной загрузке заголовки были Bestin, но после первого interaction deferred CSS мог давать короткий переход:

`Bestin → Work Sans → Bestin`

Причина: поздний Bootstrap heading rule с `font-family: inherit` на один repaint перебивал critical font.

Широкий fix:

`h1,h2,h3,h4,h5,h6 { font-family:'Bestin' !important; }`

**отклонен**, потому что ломал typography product titles.

Финальное правило:

`h1,h2,h3,h4,h5,h6{font-family:'Bestin';}`

`#content>h1,h3.text-refine{font-family:'Bestin' !important;}`

Результат подтвержден:
- главный category H1 остается Bestin без flash;
- `h3.text-refine` остается Bestin без flash;
- product titles снова используют штатный шрифт.

---

# 7. Последние critical visual fixes

### Filter icon

Поздний override сейчас:

`.fa-filter:before{width:15.72px;height:20px;display:block!important;}`

### Sidebar filter button

Для `.show-sidebar i:first-child` добавлены:

- `display:flex;`
- `align-items:center;`
- `justify-content:center;`

Это стабилизирует и центрирует filter glyph.

---

# 8. JavaScript optimization — текущий live state

## grid.js

Файл:

`catalog/view/javascript/plaza/category/grid.js`

Normal category add временно отключен в:

`system/plaza_control_panel.ocmod.xml`

`grid.js`:
- fetch после `window.load`;
- execute на первом interaction;
- object definition перенесена до ready callback;
- auto-init защищен `window.wdhLazyGridJs`, чтобы первый interaction не переключал layout в list.

Manual Grid/List работает.

## swatches.js

Для mobile category сейчас используется lazy experiment, однако практической пользы по PSI не доказано. Файл маленький, а complexity/first-click race потенциально лишние.

Это не приоритет следующей сессии; при cleanup можно вернуть normal loading и убрать только swatches lazy operation.

## ultimatemenu/menu.js

Mobile menu JS suppressed из normal mobile header path.

Loader:
- fetch после `window.load`;
- execute на first interaction.

Burger протестирован: работает с первого tap в нормальных условиях.

Desktop/tablet path не затронут.

## OCdevWizard Form Builder

На mobile category подтверждено отсутствие Form Builder UI.

Поэтому только на **mobile product-category**:
- `global.js` исключен из normal header scripts;
- `main.js` также подавлен через отдельный OCMOD condition в `system/library/ocdevwizard/form_builder/modules.php`.

Важно: `main.js` раньше инжектился слишком широко, если в БД существовала любая active display_type=5 form, даже если placeholder не использовался на текущей странице.

Текущий condition пропускает injection только когда output одновременно содержит:
- `mobile-layout`;
- `id="product-category"`.

Другие mobile pages и desktop не должны считаться ненуждающимися в Form Builder.

## common.js

`catalog/view/javascript/common.js` теперь имеет `defer` **только на mobile category** через `wdh_mobile_deferred_js.ocmod.xml`.

Проверено:
- parser-time прямых вызовов его globals в category templates не найдено;
- `cart.add`, `wishlist.add`, `compare.add` остаются доступны к пользовательскому interaction;
- функциональный тест пройден.

## Bootstrap JS

`catalog/view/javascript/bootstrap/js/bootstrap.min.js` также `defer` **только на mobile category**.

jQuery остается обычным blocking script.

Bootstrap-dependent dropdown/collapse/tooltip paths на category работают в тестах.

## Remaining render-blocking JS

После текущих изменений в PSI фактически остался основной blocking script:

`catalog/view/javascript/jquery/jquery-2.1.1.min.js`

**Не пытаться blindly defer jQuery.** На теме много inline/theme JS, зависящего от него во время parse/initialization.

---

# 9. Mobile deferred JS OCMOD

Файл:

`D:\work\WDH-files\system\wdh_mobile_deferred_js.ocmod.xml`

Он сейчас объединяет mobile-only операции для:
- suppression/lazy menu.js;
- mobile-category suppression Form Builder global.js;
- mobile-category suppression Form Builder main.js injection;
- `common.js defer` на mobile category;
- `bootstrap.min.js defer` на mobile category.

Desktop/tablet deferred JS необходимо проектировать отдельно, если до него дойдет работа. Не смешивать с mobile OCMOD.

---

# 10. PSI после JS optimization

Три representative mobile runs после `common.js defer + bootstrap defer`:

| Run | Performance | FCP | LCP | TBT | CLS | SI |
|---|---:|---:|---:|---:|---:|---:|
| 1 | 79 | 1.7 s | 4.9 s | 20 ms | 0.094 | 3.2 s |
| 2 | 79 | 1.7 s | 4.9 s | 60 ms | 0.094 | 2.7 s |
| 3 | 74 | 1.7 s | 6.0 s | 40 ms | 0.094 | 3.3 s |

Representative median примерно:
- Performance: **79**;
- FCP: **1.7 s**;
- LCP: **4.9 s**;
- TBT: **40 ms**;
- CLS: **0.094**.

PSI остается шумным. Был также slower run примерно:

- Performance: **71**;
- FCP: **2.4 s**;
- LCP: **6.9 s**;
- TBT: **80 ms**;
- CLS: **0**;
- SI: **4.2 s**.

Вывод: удаление JS из blocking path помогло, но **зеленую зону теперь ограничивает прежде всего LCP, а не TBT**.

---

# 11. Следующий главный bottleneck — product images / LCP

Lighthouse `Improve image delivery` на текущей category показал примерно:

- общий потенциальный saving: **~858 KiB**;
- первая/LCP product image: около **207.7 KiB**;
- потенциальный saving по ней: около **192.6 KiB**;
- source/rendered file: **600×600**;
- фактический display на mobile: примерно **305×305**;
- Lighthouse отдельно показывает крупный резерв от modern format и от proper sizing.

То есть category тянет 600×600 изображения для карточек, которые реально показываются примерно вдвое меньше по ширине/высоте.

LCP image уже имеет `fetchpriority="high"`, поэтому следующая задача — **уменьшить стоимость самого image resource**.

### Важно для следующей сессии

Механизм формирования product image resize **уже расследовался 06.10.2026**.

Не начинать поиск resize с нуля.

Продолжить от найденного механизма формирования 600×600 и определить безопасную архитектуру:

1. mobile-category image size / responsive source;
2. возможно `srcset/sizes`;
3. затем WebP/modern-format generation;
4. сохранить текущий `fetchpriority="high"` для LCP image;
5. после каждого шага делать causal PSI comparison.

Не делать hardcoded preload конкретного product image: первый товар зависит от category и может меняться.

---

# 12. TTFB / ptmenu

Ранее probes в `catalog/controller/product/category.php` показали дорогой `ptmenu/position1`.

Используется:

`system/wdh_ptmenu_cache.ocmod.xml`

Warm `position1` после caching был примерно **124–148 ms**.

Остается технический долг: robust invalidation ptmenu cache при изменении categories/menu structure.

В `catalog/controller/product/category.php` могут оставаться diagnostic TTFB probes.

Clean backup:

`catalog/controller/product/category.php.bak-ttfb-20261004`

Перед финальной сдачей probes должны быть убраны.

---

# 13. Активные / важные custom OCMOD

Ключевые custom OCMOD текущего проекта:

1. `system/wdh_ptmenu_cache.ocmod.xml`
2. `system/wdh_mobile_plaza_header_skip.ocmod.xml`
3. `system/wdh_category_lcp_priority.ocmod.xml`
4. `system/wdh_newsletter_inline.ocmod.xml`
5. `system/wdh_category_critical_css.ocmod.xml`
6. `system/wdh_mobile_deferred_js.ocmod.xml`

Также временно изменен vendor:

`system/plaza_control_panel.ocmod.xml`

для category `grid.js` / `swatches.js` экспериментов.

Долгосрочно желательно не оставлять permanent custom behavior как ручную правку vendor OCMOD, но не менять это во время текущего LCP этапа без необходимости.

---

# 14. Что НЕ повторять

- Не возвращать sequential delayed CSS loader — он дал CLS ~0.614.
- Не редактировать `storage/modification` напрямую.
- Не делать mass defer всех JS.
- Не defer jQuery без полного dependency plan.
- Не ставить `Bestin !important` на все `h1-h6` — это ломает product title typography.
- Не считать один transient 502 при OCMOD Refresh доказательством ошибки.
- Не делать hardcoded preload конкретного LCP product image.
- Не начинать заново расследование image resize — оно уже проводилось сегодня.

---

# 15. Ближайший следующий шаг

**Начать следующую сессию с product image delivery / LCP.**

Порядок:

1. Восстановить из текущего контекста уже найденное место/механизм, формирующий resize 600×600 для category product images.
2. Не менять ничего до подтверждения текущей логики.
3. Спроектировать mobile-safe уменьшенный/responsive image output.
4. Проверить LCP image network size и rendered dimensions.
5. Провести 3 PSI на одном и том же состоянии.
6. Только после proper sizing рассматривать WebP/AVIF generation.
7. Не трогать стабильный deferred CSS loader и текущие JS defer без конкретной причины.

---

# 16. Остаточные задачи проекта

- product image sizing / responsive images / WebP;
- дальнейшее снижение LCP/FCP;
- robust invalidation ptmenu cache;
- убрать TTFB probes перед финалом;
- cleanup swatches lazy experiment, если он останется без доказанной пользы;
- вынести permanent grid behavior из vendor OCMOD edit в более чистую custom architecture;
- проверить persistence всех custom OCMOD после Refresh;
- обновить `AGENTS.md`, `docs/TASK.md`, `docs/AUDIT.md`, `docs/OCMOD_RULES.md`, `docs/CHANGELOG.md` ближе к финалу;
- провести final PSI series для homepage/category/product.

## Главный принцип

Все финальные оптимизации должны переживать **OCMOD Refresh** и не должны зависеть от ручных изменений generated `storage/modification` files.
