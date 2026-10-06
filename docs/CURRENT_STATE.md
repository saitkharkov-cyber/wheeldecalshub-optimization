# CURRENT STATE

Дата: **05.10.2026**

## Проєкт

Сайт: https://wheeldecalshub.com/  
CMS: OpenCart 3.0.3.2  
PHP: 7.3.33  
Тема: `tt_uren1`

Локальний репозиторій: `D:\Git\wheeldecalshub-optimization`  
Повна локальна копія сайту: `D:\work\WDH-files\`

## Робочий протокол

- Працювати по одному маленькому безпечному кроку.
- Після кожного кроку чекати `++` або результат користувача.
- PowerShell-команди давати **одним фізичним рядком**.
- Перед змінами існуючих файлів: backup + точна перевірка match/count.
- Не робити широкі replace без перевірки.
- UTF-8 без BOM.
- Не редагувати `storage/modification/` напряму.
- Постійні зміни повинні переживати **OCMOD Refresh**.
- Локальні зміни робимо у `D:\work\WDH-files`, потім upload на hosting і OCMOD Refresh.
- Поточний фокус: CSS architecture / first-screen rendering. Не повертатися без потреби до широкого JS/PHP-header розслідування.

## Baseline Mobile PageSpeed / Lighthouse

| Тип сторінки | Performance | FCP | LCP | TBT | CLS | SI |
|---|---:|---:|---:|---:|---:|---:|
| Головна | 44 | 6.8 s | 8.8 s | 0 ms | 0.285 | 6.8 s |
| Каталог | 35 | 7.4 s | 12.9 s | 0 ms | 0.704 | 7.4 s |
| Картка товару | 45 | 8.5 s | 9.6 s | 200 ms | 0.194 | 8.5 s |

Baseline-скриншоти збережено в `measurements/baseline/`.

## Каталог — підтверджені результати

### CLS

Основну причину високого CLS локалізовано: товари сервером віддавалися як list, після чого `grid.js` перебудовував їх у grid.

Після server-side initial grid та резервування місця під зображення CLS знижено приблизно з **0.704** до **~0.09**. В одному стабільному PSI-run CLS був **0**.

Поточні класи картки товару:

`product-layout product-grid grid-style col-lg-3 col-md-3 col-sm-3 col-xs-6 product-items`

Для product thumbs:

`width="600" height="600"`

Payment image:

`width="350" height="30"`

### LCP priority

Через:

`system/wdh_category_lcp_priority.ocmod.xml`

першим product images додається:

`fetchpriority="high"`

Поточний LCP element — перша product image ABARTH 500.

## LCP image — важная диагностическая точка

На mobile category LCP стабильно является первая product image:

ABARTH 500 3d car stickers, emblems, decals for wheel center caps replacements

HTML:
- width="600"
- height="600"
- fetchpriority="high"

Пример LCP breakdown:
- Time to First Byte: 0 ms
- Resource load delay: 700 ms
- Resource load duration: 80 ms
- Element render delay: 370 ms

Важно:
- этот же LCP image уже неоднократно проверялся;
- fetchpriority="high" присутствует;
- Lighthouse больше не сообщает прежнюю проблему late LCP discovery;
- Resource load delay сильно плавает между PSI-запусками: ранее наблюдалось около 2560 ms, в другом запуске около 700 ms;
- поэтому нельзя считать поздний discovery доказанной постоянной причиной без серии повторных замеров.

## TTFB / ptmenu

Діагностичними probes у `catalog/controller/product/category.php` встановлено, що дорогим етапом був `ptmenu/position1`.

Виявлено:

- модуль `ptmenu.227`;
- Vertical Menu 01;
- menu id `4`;
- категорії `59/60` з `show_child`;
- приблизно 154 immediate child links.

Додано кешування custom ptmenu items через:

`system/wdh_ptmenu_cache.ocmod.xml`

Після кешування warm hits `position1` були приблизно **~124–148 ms**. Звичайний TTFB категорії — приблизно **0.97–1.13 s**.

### Невирішене

Cache key враховує store/menu/language/settings, але ще не має надійної invalidation при зміні категорій або структури меню.

## Діагностичні TTFB probes

У `catalog/controller/product/category.php` ще залишаються probes.

Чистий backup:

`catalog/controller/product/category.php.bak-ttfb-20261004`

Перед фінальними вимірами:
1. відновити clean backup;
2. OCMOD Refresh;
3. перевірити функціональність;
4. тільки потім фінальна PSI-серія.

## Header / mobile

Через:

`system/wdh_mobile_plaza_header_skip.ocmod.xml`

оптимізовано mobile header path. Ефект за probes був близько **~100 ms**.

Після поточної ручної дополіровки critical-only mobile header користувач підтвердив:

**«шапка идеальная»**.

## Newsletter

Через:

`system/wdh_newsletter_inline.ocmod.xml`

прибрано окреме initial завантаження tiny `mail.js`.

Перевірено:
- `mail.js` окремо не вантажиться;
- `typeof ptnewsletter === 'object'`.

## Critical CSS — основний файл

`catalog/view/theme/tt_uren1/stylesheet/critical-category-mobile.css`

Critical значно розширений та містить:
- Bootstrap-used beta foundation;
- mobile/category overrides;
- локальні шрифти;
- inline SVG masks для критичних іконок;
- header / breadcrumb / sidebar / product-grid first-screen rules.

## Поточний OCMOD CSS-тест

Файл:

`system/wdh_category_critical_css.ocmod.xml`

Поточна тестова версія:

`1.2-test-critical-inline`

На mobile category цей тест:
1. вставляє весь `critical-category-mobile.css` **inline у `<style>` в `<head>`**;
2. пригнічує initial завантаження звичайних Bootstrap/theme/icon CSS;
3. використовується як контрольний експеримент для максимального critical-only first screen.

Це **не фінальна архітектура**, а поточна тестова точка.

## Critical-only контрольний експеримент

Перший critical-only test до повної візуальної дополіровки:

- Performance: **83**
- FCP: **1988 ms**
- SI: **2460 ms**
- LCP: **4351 ms**
- TBT: **105 ms**
- CLS: **0.05**

Це довело великий потенціал від повного прибирання некритичних CSS з initial path.

Після дополіровки останній PSI-run:

- Performance: **71**
- FCP: **2714 ms**
- SI: **5248 ms**
- LCP: **5251 ms**
- TBT: **41 ms**
- CLS: **0.09**

Один запуск не вважається доказом регресу через шум PSI. Потрібен повторний causal test без змін.

## LCP breakdown — останній PSI

LCP element:
перша product image ABARTH 500, `600x600`, `fetchpriority="high"`.

Breakdown:
- Time to First Byte: **0 ms**
- Resource load delay: **2560 ms**
- Resource load duration: **40 ms**
- Element render delay: **180 ms**

Ключова поточна проблема — **дуже пізній старт запиту LCP image**, а не швидкість завантаження картинки чи render delay.

Перед новими змінами потрібно повторити PSI на тому самому стані. Якщо `Resource load delay` знову буде близько **2–2.5 s**, тоді окремо досліджувати, чому браузер пізно починає request LCP image, незважаючи на `fetchpriority="high"`.

## JS — важливий контекст

Lighthouse все ще показує parser/render-blocking JS, зокрема:
- `bootstrap.min.js`
- `form_builder/main.js`
- `swatches/swatches.js`
- `jquery/jquery-2.1.1.min.js`
- `javascript/common.js`
- `form_builder/global.js`
- `category/grid.js`
- `ultimatemenu/menu.js`

JS вже окремо досліджувався; простий defer небезпечний через inline calls, що очікують jQuery.

**Не повертатися зараз до широкого JS-рефакторингу**, поки не підтверджена повторюваність LCP delay.

## Bestin

Critical використовує `@font-face` для `Bestin`.

Файл:

`catalog/view/theme/tt_uren1/stylesheet/plaza/bestin.ttf`

Через те, що critical тепер inline, старий відносний URL `plaza/bestin.ttf` був неправильний.

Поточний URL:

`catalog/view/theme/tt_uren1/stylesheet/plaza/bestin.ttf`

Також у critical є:

`h1,h2,h3,h4,h5,h6{font-family:'Bestin';}`

## Work Sans

Google Fonts dependency для first screen замінена локальними WOFF2:

`catalog/view/theme/tt_uren1/fonts/worksans/WorkSans-Regular.woff2`

`catalog/view/theme/tt_uren1/fonts/worksans/WorkSans-SemiBold.woff2`

В critical є `@font-face` для 400 і 600.

Body override:

`body{font-family:'Work Sans',sans-serif;}`

## Critical icons — font icons → SVG masks

Для first screen знайдено реальні glyphs:
1. hamburger — Ionicons;
2. search — Ionicons;
3. settings — Ionicons;
4. cart — Ionicons;
5. home — Font Awesome;
6. filter — Font Awesome;
7. brand arrow — Glyphicons.

Cart arrow на mobile прихований:

`#cart>.btn:after{content:none!important;display:none!important;}`

Glyphs витягнуті з оригінальних TTF через `fontTools`.

Після clipping cart усі SVG перегенеровано по **реальному glyph bounding box**.

ViewBox:
- `hamburger.svg` — `384 x 256`
- `search.svg` — `384 x 384`
- `settings.svg` — `417.2 x 416`
- `cart.svg` — `448 x 448`
- `cart-arrow.svg` — `320 x 192` (на mobile не використовується)
- `home.svg` — `1612.222... x 1283`
- `filter.svg` — `1410.041... x 1408`
- `brand-arrow.svg` — `1101 x 750`

Файли:

`catalog/view/theme/tt_uren1/images/critical-icons/`

SVG вбудовані прямо у CSS як `data:image/svg+xml` masks.

Перевірено:
- `Inline SVG data URIs: 14`
- 7 активних іконок × `-webkit-mask` + `mask`
- зовнішніх critical-icons requests у critical немає.

## Header polish — фінальні critical overrides

Додано точні dimensions/limits для:
- `#cart > .btn`;
- `.box-setting > button`;
- `.box-setting > button:before`;
- `.col-cart`;
- cart pseudo;
- settings pseudo;
- search pseudo;
- `.fa-home:before`.

Після цього header візуально підтверджений як ідеальний.

## Breadcrumb / sidebar / category first-screen polish

У critical додано/уточнено:
- breadcrumb reset;
- `.breadcrumbs,.breadcrumbs .container{max-height:46px;margin-bottom:5rem;}`;
- `.ajax-loader`;
- `.show-sidebar i:first-child`;
- `.show-sidebar i:last-child`;
- `.layered-navigation-block{display:none;}`;
- `#content>h1{margin-top:0;text-transform:uppercase;letter-spacing:0;}`.

## Bootstrap-used beta foundation

05.10.2026 вручну з Chrome Coverage відібрано фактично використовувані Bootstrap-правила.

Окремий beta-файл:

`bootstrap-used-beta.css`

Його вміст додано **на початок**:

`critical-category-mobile.css`

Маркер:

`/* Bootstrap used beta */`

У foundation входять реально використані reset/base, grid, forms, buttons, input groups, breadcrumb, pagination, panels, clearfix, visibility helpers.

Ручні theme overrides розташовані нижче beta-блоку.

## Sequential delayed CSS experiment — НЕ ПОВТОРЮВАТИ

Було реалізовано sequential delayed CSS loader зі збереженням cascade order.

Результат:
- сильні staged repaints;
- поетапна перебудова сторінки;
- CLS приблизно **0.614**.

Експеримент відкочено.

**Не повторювати serial delayed CSS loader у такому вигляді.**

Backups:
- `D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml.bak-before-sequential-css-loader-20261005`
- `D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml.bak-sequential-loader-20261005`
- `D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml.bak-before-css-loader-20261005`

## Backup перед critical-only test

`D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml.bak-before-critical-only-test-20261005`

## Старий стабільний PSI до critical-only

Після rollback sequential loader стабільна схема v1.0 давала приблизно:

- Performance: **57**
- FCP: **7.8 s**
- SI: **7.8 s**
- LCP: **13.1 s**
- TBT: **0**
- CLS: **0**

Це головна контрольна точка для порівняння з critical-only.

## Активні кастомні OCMOD

1. `system/wdh_ptmenu_cache.ocmod.xml`
2. `system/wdh_mobile_plaza_header_skip.ocmod.xml`
3. `system/wdh_category_lcp_priority.ocmod.xml`
4. `system/wdh_newsletter_inline.ocmod.xml`
5. `system/wdh_category_critical_css.ocmod.xml`

Усі фінальні зміни повинні перевірятися через OCMOD Refresh.

## Hosting caveat

05.10.2026 під час OCMOD Refresh hosting повернув:

`502 Bad Gateway`

Потім hosting показав власну сторінку:

`Тимчасове перевантаження / Обробка запиту...`

Для цього hosting таке трапляється.

Не вважати одиночний 502 доказом помилки OCMOD. Не натискати Refresh багато разів поспіль. Дочекатися стабілізації hosting і повторити Refresh один раз.

## Theme / cache workflow

**upload → OCMOD Refresh → Theme Refresh за потреби → raw HTML/DOM → Slow 3G/functionality → PSI**

Generated-файли `storage/modification/` напряму не редагувати.

## Найближчий наступний крок

1. Дочекатися, поки hosting перестане показувати temporary overload.
2. Якщо останній OCMOD Refresh не завершився успішно — один раз повторити Refresh.
3. Перевірити mobile category візуально.
4. **Не змінюючи код**, повторити PSI.
5. Подивитися LCP breakdown.
6. Якщо `Resource load delay` знову близько **2–2.5 s**, досліджувати саме late LCP request/discovery.
7. Якщо delay повернеться до ~0.7–1.0 s, вважати попередній PSI noisy run і зробити ще кілька вимірів.
8. Поки не підключати full CSS по interaction і не повертатися до JS defer.

## Архітектурний напрямок після стабілізації critical

Поточний експеримент довів сильний напрямок:

**inline critical → first screen повністю коректний → решта CSS не повинна конкурувати з initial rendering**

Але serial delayed loader показав неприйнятні staged repaints.

Тому final CSS loading architecture потрібно проєктувати окремо після стабілізації critical і LCP, без повторення невдалого sequential loader.

## Залишкові задачі проєкту

- підтвердити повторюваність LCP resource load delay;
- спроєктувати безпечну final CSS loading architecture;
- robust invalidation для ptmenu cache;
- прибрати TTFB probes;
- фінально вирішити `config_product_count`;
- фінально вирішити стан Plaza filter;
- перевірити persistence всіх custom OCMOD після Refresh;
- прибрати вже непотрібні critical icon font dependencies після остаточної SVG-перевірки;
- оновити `AGENTS.md`;
- оновити `docs/TASK.md`;
- оновити `docs/AUDIT.md`;
- оновити `docs/OCMOD_RULES.md`;
- оновити `docs/CHANGELOG.md`;
- провести фінальні PSI series для homepage/category/product;
- зафіксувати завершені зміни в Git.

## Правило

Усі фінальні оптимізації повинні переживати **OCMOD Refresh**.

Generated-файли `storage/modification/` не використовувати як джерело постійних змін.

---

# CURRENT UPDATE — 2026-10-06

This section supersedes stale "next step" notes above where they conflict with the state below.

## Current category optimization state

Target: mobile OpenCart category in critical-only mode.

Active OCMOD:
`system/wdh_category_critical_css.ocmod.xml`

Version:
`1.2-test-critical-inline`

Normal Bootstrap/theme/icon CSS is intentionally suppressed on the initial mobile category render. The required first-screen CSS is inline in `<head>`.

Do NOT return yet to broad JS/defer work. First finish/stabilize the critical-only visual state.

## Latest PSI / LCP observations

Recent representative mobile PSI:
- Performance: 77
- FCP: 2.3 s
- Speed Index: 2.5 s
- LCP: 4.9 s
- TBT: 20 ms
- CLS: 0.095

LCP element is the first product image (ABARTH 500), 600x600, with `fetchpriority="high"`.

Observed LCP Resource Load Delay:
- about 700 ms
- about 690 ms
- one isolated bad run about 2560 ms

Therefore late LCP discovery is NOT currently confirmed. Treat the 2560 ms run as likely noise unless it repeats consistently.

## Critical visual fixes completed 2026-10-06

### Accordion / Select Your Brand

Source styles were restored for:
- `#accordioncat .panel-heading`
- accordion link padding
- right-aligned arrow
- typography

A CSS parse problem was found and fixed: there was an extra standalone `}` after the Bestin `@font-face`. Because of that parser error the base `#accordioncat .panel-heading` rule was absent from CSSOM even though it existed in raw inline CSS.

After removing the extra brace the accordion matches the reference visually.

### Typography

Critical typography fixes include:
- `h1` 50px
- `h3` 50px
- `h4` 26px
- `h6` 24px
- `.text-refine` 1.6rem / uppercase / weight 500
- filter icon dimensions adjusted
- `#accordioncat .panel-heading a` 26px

### Toolbar

Mobile toolbar was restored to visually match the full-CSS reference.

Important responsive visibility from original stylesheet:
- max-width 1199px: hide `.btn-grid-4`, `.btn-grid-5`
- max-width 767px: hide `.btn-grid-3`
- at the tested 440px width visible controls are therefore grid-1, grid-2 and list.

Advanced view is active in the template (`use_advance_view`), with six buttons existing in DOM before responsive hiding.

Toolbar PNG icons needed for initial critical render were embedded as `data:image/png;base64` instead of external image requests.

Sort/limit critical styles were restored, including rounded selects and hidden mobile labels.

Toolbar now visually appears to match the reference.

## Critical CSS single source of truth

IMPORTANT NEW WORKFLOW:

`catalog/view/theme/tt_uren1/stylesheet/critical-category-mobile.css`

is now the ONLY file that should be edited manually for category critical CSS.

Do NOT manually duplicate future CSS edits inside:
`system/wdh_category_critical_css.ocmod.xml`

Repository helper script created:

`scripts/sync-critical-css.ps1`

It reads:
`D:\work\WDH-files\catalog\view\theme\tt_uren1\stylesheet\critical-category-mobile.css`

and replaces the single `<style>...</style>` block in:
`D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml`

The script:
- requires exactly one `<style>` block;
- makes a timestamped OCMOD backup;
- writes UTF-8 without BOM.

Because local PowerShell execution policy blocks direct `.ps1` execution, run it with:

`powershell.exe -NoProfile -ExecutionPolicy Bypass -File 'D:\Git\wheeldecalshub-optimization\scripts\sync-critical-css.ps1'`

Last test:
- STYLE BLOCKS: 1
- SYNCED
- XML VALID

Last generated backup:
`D:\work\WDH-files\system\wdh_category_critical_css.ocmod.xml.bak-before-sync-20261006-130404`

## Workflow from now on

For critical CSS changes:

1. Backup / exact-match check as usual.
2. Edit only `critical-category-mobile.css`.
3. Run `scripts/sync-critical-css.ps1`.
4. Validate OCMOD XML.
5. Upload updated `wdh_category_critical_css.ocmod.xml`.
6. Perform ONE OCMOD Refresh.
7. Verify visual/functionality.
8. Run PSI only after the visual state is stable.

Never edit `storage/modification` directly.

Hosting can intermittently return temporary overload / 502 during OCMOD Refresh. One isolated 502 does not prove an OCMOD error. Do not spam Refresh.

## Do NOT repeat

Sequential delayed CSS loading was tested and caused staged repainting and CLS around 0.614.

Do NOT repeat that implementation.

## Immediate next task

Critical-only category is now substantially visually restored, including header, accordion, typography and toolbar.

Next:
1. Continue visual comparison of the remaining category screen against full CSS.
2. Fix any remaining critical-only differences.
3. Only after critical visual parity is complete, design the safe mechanism for loading the remaining non-critical CSS after first interaction.
4. Do not return to broad JS/defer optimization before that.
