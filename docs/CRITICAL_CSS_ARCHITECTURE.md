# Архітектура critical CSS

## Правило мови документації

Документація проєкту ведеться українською мовою.

Англійською допускаються лише технічні терміни та усталені поняття (`style`, `script`, `stylesheet`, `defer`, `preload`, `critical CSS`, `source of truth`, `inline`, `render-blocking`), назви файлів, директорій, класів, CSS-селекторів, HTML-атрибутів, фрагменти коду, команди, назви API та бібліотек.

Пояснювальний текст, заголовки, коментарі та опис архітектурних рішень мають бути українською мовою.

## Призначення

Цей документ описує архітектуру critical CSS і пов’язаного з ним відкладеного завантаження повних стилів у проєкті Wheeldecalshub.

Основна мета — забезпечити швидкий перший рендер без втрати візуальної коректності, уникнути ручного дублювання великих CSS-блоків усередині OCMOD та зберегти всі зміни після OCMOD Refresh.

## Поточна архітектура

Поточна цільова схема:

`CSS source-файли → PHP loader → controller data → Twig → inline <style>`

OCMOD більше не повинен бути місцем зберігання десятків кілобайт critical CSS.

OCMOD використовується лише як інтеграційний шар, який:

- підключає PHP loader;
- передає зібраний critical CSS у `$data`;
- додає одну невелику Twig-вставку у потрібний `<head>`.

Сам CSS зберігається у звичайних `.css` файлах і читається loader-ом під час формування сторінки.

## Source of truth

Єдиним практичним source of truth для critical CSS мають бути файли:

- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-common-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-category-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-product-mobile.css`

Надалі за потреби можуть бути додані desktop-еквіваленти, наприклад:

- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-common-desktop.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-category-desktop.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-product-desktop.css`

Ці файли повинні бути присутні на production, оскільки PHP loader читає їх безпосередньо з файлової системи.

Не можна вручну підтримувати один і той самий CSS одночасно в `.css` файлі та всередині OCMOD XML.

## PHP loader

Loader:

`system/library/wdh/critical_css.php`

Його відповідальність:

- визначити, чи потрібен critical CSS для поточного route;
- прочитати потрібні CSS source-файли;
- зібрати їх у правильному порядку;
- повернути один inline `<style>` без додаткового HTTP-запиту.

Поточний mobile loader працює для:

- `product/category`
- `product/product`

Поточний wrapper:

`<style id="wdh-critical-mobile" media="(max-width:991px)">`

Якщо source-файл відсутній або порожній, loader не повинен ламати сторінку.

## Інтеграційний OCMOD

Інтеграційний OCMOD:

`system/wdh_critical_css_loader.ocmod.xml`

Він не містить великих CSS-блоків.

Поточна логіка:

1. у `catalog/controller/common/header.php` після отримання header scripts підключається `system/library/wdh/critical_css.php`;
2. визначається поточний `route`;
3. результат loader-а записується у `$data['wdh_critical_css']`;
4. у mobile header після `<base href="{{ base }}" />` виводиться:

```twig
{% if wdh_critical_css %}{{ wdh_critical_css|raw }}{% endif %}
```

Ця схема вже перевірена на live product page.

## Точки впровадження mobile і desktop

### Mobile

Mobile-сторінки використовують:

`catalog/view/theme/tt_uren1/template/plaza/page_section/header_mobile.twig`

Plaza Control Panel вибирає цей шаблон для mobile через `Mobile_Detect`.

Loader формує `$data['wdh_critical_css']` у `catalog/controller/common/header.php`, тому те саме значення доступне незалежно від того, який header view буде обрано далі.

### Desktop

Desktop використовує справжній `<head>` із:

`catalog/view/theme/tt_uren1/template/common/header.twig`

Desktop critical CSS у майбутньому має інтегруватися саме туди.

Не можна вставляти desktop critical лише у `plaza/page_section/header/header1.twig`, тому що цей шаблон є body-фрагментом, а не справжнім `<head>` документа.

На першому етапі міграції desktop не змінюється.

## Порядок шарів

Порядок складання critical CSS принциповий:

`common → page-specific`

Тобто:

- category: `critical-common-mobile.css` → `critical-category-mobile.css`;
- product: `critical-common-mobile.css` → `critical-product-mobile.css`.

Page-specific файл не повинен дублювати весь common шар.

Він має містити тільки правила, специфічні для конкретного типу сторінки.

## Загальний mobile шар

`critical-common-mobile.css`

Містить тільки спільні правила першого екрана, які однаково потрібні на category і product.

До цього шару можуть входити:

- базова типографіка;
- мінімально необхідні Bootstrap-правила;
- header;
- logo;
- search;
- cart;
- settings;
- mobile menu;
- breadcrumbs;
- спільна геометрія;
- SVG-заміни іконок, які потрібні до завантаження icon fonts.

У цей файл не повинні потрапляти category-specific або product-specific правила.

## Mobile category

`critical-category-mobile.css`

Має містити тільки category-specific правила, наприклад:

- toolbar;
- filter / accordion;
- off-canvas columns;
- category-specific product cards;
- category-only іконки;
- responsive category layout.

Він не повинен повторювати common mobile шар.

## Mobile product

`critical-product-mobile.css`

Має містити тільки product-specific правила, необхідні для першого видимого екрана, наприклад:

- product gallery;
- thumbnails;
- назву товару;
- rating;
- price;
- product options;
- quantity / buy controls, якщо вони потрапляють у перший екран;
- responsive product layout.

Reviews, related products та нижні блоки не потрібно додавати без підтвердження через Coverage або фактичний first render.

## Міграція зі старого монолітного OCMOD

Старий файл:

`system/wdh_category_critical_css.ocmod.xml`

історично містить великі inline-блоки critical CSS для mobile і desktop.

Міграція виконується поступово.

Правило безпечного перенесення кожного фрагмента:

1. визначити маленький однозначний CSS-фрагмент;
2. додати його у відповідний новий source-файл;
3. перевірити, що loader реально виводить його у live HTML;
4. тимчасово допустити дублювання старого і нового правила;
5. перевірити візуальний рендер і Console;
6. зробити backup старого XML;
7. видалити лише підтверджену mobile-копію зі старого XML;
8. виконати OCMOD Refresh;
9. перевірити live HTML повторно;
10. тільки після цього переходити до наступного фрагмента.

Не можна одночасно переносити великий шар CSS або видаляти старий monolith до підтвердження нового шляху.

## Перший підтверджений перенос

Першим пілотним фрагментом стали два `@font-face` для Work Sans.

Вони були винесені у:

`catalog/view/theme/tt_uren1/stylesheet/wdh/critical-common-mobile.css`

Після перевірки нового loader-а mobile-копії цих `@font-face` були видалені зі старих category та product inline-блоків у `wdh_category_critical_css.ocmod.xml`.

Desktop-копія залишена без змін.

Таким чином перший реальний фрагмент уже має новий source of truth і успішно проходить через ланцюжок:

`critical-common-mobile.css → WdhCriticalCss → $data['wdh_critical_css'] → header_mobile.twig → inline <style>`

## Відкладене завантаження повних CSS

На category вже використовується механізм:

`<template class="wdh-deferred-css">`

Повні CSS-посилання не беруть участі в першому рендері як звичайні `rel="stylesheet"`.

Після `window.load` вони готуються як `preload`, а реальний stylesheet активується після взаємодії користувача.

Поточні події активації:

- `pointerdown`
- `touchstart`
- `keydown`
- `wheel`

Critical CSS loader і deferred CSS — це різні відповідальності.

У перспективі deferred-логіку слід винести з монолітного XML в окремий невеликий OCMOD, наприклад:

`system/wdh_deferred_css.ocmod.xml`

Але це не робиться одночасно з першими кроками міграції critical CSS.

## Політика іконок

Critical CSS не повинен залежати від icon fonts, якщо самі icon fonts завантажуються пізніше.

До них належать Ionicons, Font Awesome і Plaza Icon.

Іконки першого екрана мають бути замінені на inline SVG або SVG-mask.

Спільні mobile SVG-заміни повинні жити у common шарі.

Category-only іконки мають залишатися у category-specific critical, product-only — у product-specific critical.

## Робота з Coverage

Chrome Coverage використовується як фільтр для формування page-specific critical.

Не можна сліпо копіювати великі блоки CSS.

Правильна послідовність:

1. визначити вихідний CSS-файл;
2. знайти точний source-блок;
3. перевірити використання правил через Coverage;
4. взяти лише правила першого екрана;
5. зберегти правильний cascade і порядок;
6. перевірити візуальний first render;
7. лише після цього відкладати повний CSS.

## Важливе зауваження щодо cascade

Загальний critical може містити широкі правила, які в повній темі пізніше перевизначаються page-specific CSS.

Після deferred завантаження повних CSS такі перевизначення мають бути вже в page-specific critical.

Особлива увага потрібна для:

- `h1` та інших заголовків;
- font-size;
- margins;
- product layout;
- responsive media rules;
- pseudo-elements іконок;
- правил з `!important`.

Не можна вважати, що більш специфічний селектор автоматично переможе загальне правило, якщо загальне правило містить `!important`.

## Постійність змін через OCMOD

Не можна редагувати `storage/modification/` напряму.

Будь-яка правка, яка має пережити OCMOD Refresh, повинна існувати у вихідному файлі, PHP loader або OCMOD XML.

`storage/modification/` використовується тільки для перевірки результату застосування модифікаторів.

## Модель викладання на server

Для нової архітектури на production повинні бути присутні щонайменше:

- `system/library/wdh/critical_css.php`;
- потрібні `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-*.css`;
- встановлений `system/wdh_critical_css_loader.ocmod.xml`.

Після зміни loader OCMOD:

1. встановити або оновити OCMOD;
2. виконати OCMOD Refresh;
3. очистити Twig/modification cache за потреби;
4. перевірити modified controller і modified Twig;
5. перевірити live HTML;
6. перевірити візуальний рендер;
7. перевірити Console;
8. лише після цього перевіряти PageSpeed.

Після зміни тільки `.css` source-файлу OCMOD Refresh не потрібен, якщо структура інтеграції loader-а не змінювалася.

## Deployment-пакування

Поточний пілот допускає ручне завантаження `critical_css.php` та `.css` source-файлів на сервер.

Довгостроково бажано, щоб installer-пакет містив не лише `install.xml`, а й файли, які повинні бути розгорнуті на production, наприклад через `upload/` структуру пакета.

Це зменшить ризик ситуації, коли OCMOD уже активний, але loader або CSS source-файл ще не завантажений.

## Правило подальшої роботи

До завершення міграції старий `wdh_category_critical_css.ocmod.xml` залишається активним.

Кожен наступний фрагмент переноситься окремо і видаляється зі старого XML тільки після live-перевірки нового source-файлу.

Не потрібно одночасно:

- переписувати весь critical CSS;
- міняти desktop;
- переносити deferred CSS;
- виконувати нову PageSpeed-оптимізацію.

Спочатку потрібно безпечно розділити поточний моноліт на підтримувані source-файли та довести нову архітектуру до стабільного стану.

## Кешування runtime critical CSS

Щоб не читати та не об'єднувати source CSS-файли на кожному HTTP-запиті, loader використовує файловий кеш у `DIR_STORAGE`.

Каталог кешу:

`DIR_STORAGE/cache/wdh_critical_css/`

Плановані файли:

- `category-mobile.css`
- `product-mobile.css`

Правила складання:

- `category-mobile.css = critical-common-mobile.css + critical-category-mobile.css`
- `product-mobile.css = critical-common-mobile.css + critical-product-mobile.css`

Кеш зберігає лише чистий CSS без `<style>...</style>`.

### Інвалідація кешу

TTL не використовується.

Кеш перебудовується тільки якщо:

1. cache-файл ще не існує;
2. будь-який source CSS-файл має `filemtime()` новіший за cache-файл.

Якщо source CSS не змінювався, loader читає вже готовий cache-файл і не виконує повторне складання.

Якщо page-specific source-файл ще відсутній, допускається складання лише з `critical-common-mobile.css`.

Якщо source-файл тимчасово недоступний, існуючий валідний кеш не повинен перезаписуватися порожнім вмістом.

Обгортка `<style>` формується loader-ом уже під час повернення результату в шаблон.