# Архітектура critical CSS

## Правило мови документації

Документація проєкту ведеться українською мовою.

Англійською допускаються лише технічні терміни та усталені поняття (`style`, `script`, `stylesheet`, `defer`, `preload`, `critical CSS`, `source of truth`, `inline`, `render-blocking`), назви файлів, директорій, класів, CSS-селекторів, HTML-атрибутів, фрагменти коду, команди, назви API та бібліотек.

Пояснювальний текст, заголовки, коментарі та опис архітектурних рішень мають бути українською мовою.

## Призначення

Цей документ описує архітектуру critical CSS і відкладеного завантаження повних стилів у проєкті Wheeldecalshub.

Основна мета — забезпечити швидкий перший рендер без втрати візуальної коректності та зберегти всі зміни після OCMOD Refresh.

## Source of truth

Локальні файли:

- `catalog/view/theme/tt_uren1/stylesheet/critical-common-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/critical-category-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/critical-common-desktop.css`
- `catalog/view/theme/tt_uren1/stylesheet/critical-category-desktop.css`
- майбутні product-specific critical CSS

є робочими вихідними файлами для розробки.

Вони не є обов’язковими на production і зазвичай не завантажуються на сервер.

Production critical CSS вбудовується inline через OCMOD, насамперед через:

`system/wdh_category_critical_css.ocmod.xml`

Довгострокове правило:

> Critical CSS редагується в локальних source-файлах, після чого inline-блок усередині OCMOD має синхронізуватися або генеруватися з них автоматично. Один і той самий CSS не можна вручну підтримувати одночасно у двох місцях.

## Постійність змін через OCMOD

Усі постійні зміни мають знаходитися або у вихідних файлах сайту, або в OCMOD XML.

Не можна редагувати `storage/modification/` напряму.

Будь-яка правка, яка має пережити OCMOD Refresh, повинна існувати у вихідному файлі або в OCMOD.

## Точки впровадження mobile і desktop

### Mobile

Mobile-сторінки використовують `header_mobile.twig`, який підключається через `plaza_control_panel.ocmod.xml`.

Mobile critical CSS вставляється туди для потрібних типів сторінок.

Поточні префікси route/class:

- категорія: `product-category`
- товар: `product-product`

### Desktop

Desktop використовує справжній `<head>` із:

`catalog/view/theme/tt_uren1/template/common/header.twig`

Desktop critical CSS має вставлятися саме в `common/header.twig`.

Не можна розміщувати desktop critical лише в `plaza/page_section/header/header1.twig`, тому що цей шаблон є body-фрагментом, а не справжнім `<head>` документа.

## Шари critical CSS

Архітектура будується шарами.

### Загальний mobile шар

`critical-common-mobile.css`

Містить спільні стилі першого екрана:

- мінімально необхідну частину Bootstrap;
- базову типографіку;
- header;
- logo;
- search;
- cart;
- settings;
- mobile menu;
- breadcrumbs;
- спільну геометрію;
- SVG-заміни іконок, які потрібні до завантаження icon fonts.

У цей файл не повинні потрапляти category-specific або product-specific правила.

### Mobile category

`critical-category-mobile.css`

Містить загальний mobile шар плюс toolbar категорії, фільтри, category-specific картки товарів, category-only іконки та потрібні accordion/filter правила.

### Mobile product

Product page має використовувати загальний mobile critical і окремий product-specific critical для першого видимого екрана.

Product-specific critical має містити лише те, що потрібно до активації повних CSS, наприклад:

- основну product gallery;
- thumbnails;
- назву товару;
- rating;
- price;
- product options;
- quantity / buy controls, якщо вони потрапляють у перший екран;
- responsive product layout.

Reviews, related products і нижні блоки не потрібно додавати без підтвердження через Coverage.

## Відкладене завантаження повних CSS

На category вже використовується механізм:

`<template class="wdh-deferred-css">`

Повні CSS-посилання не беруть участі в першому рендері як звичайні `rel="stylesheet"`.

Після `window.load` JavaScript перетворює їх на `rel="preload" as="style"`.

Реальний `stylesheet` активується після першої взаємодії користувача.

Використовувані події:

- `pointerdown`
- `touchstart`
- `keydown`
- `wheel`

Той самий механізм можна використовувати на product page лише після того, як product critical стане достатнім для коректного першого рендеру.

Не можна вмикати defer для всіх CSS раніше, ніж critical CSS повністю покриє перший екран.

## Політика іконок

Critical CSS не повинен залежати від icon fonts, якщо самі icon fonts завантажуються пізніше.

До них належать Ionicons, Font Awesome і Plaza Icon.

Іконки першого екрана мають бути замінені на inline SVG або SVG-mask.

Поточні спільні mobile SVG-заміни:

- menu;
- settings;
- search;
- cart;
- home у breadcrumbs.

Category-only іконки, наприклад filter і accordion, залишаються в category-specific critical.

Це дозволяє уникнути додаткових font-запитів, flash відсутніх іконок, layout shift і затримки першого paint.

## Поточний стан mobile product

На product mobile вже існує inline-блок:

`<style id="critical-product-mobile" media="(max-width:991px)">`

Загальний mobile header/base шар уже підключений.

При цьому повні product CSS поки що завантажуються звичайними stylesheet-посиланнями.

Product route не можна повністю переводити на deferred CSS, доки не додано і не перевірено product-specific critical.

Правильний порядок:

1. завершити загальний mobile critical;
2. замінити потрібні іконки першого екрана на SVG;
3. додати product-specific critical;
4. перевірити перший рендер візуально;
5. лише після цього вмикати deferred CSS для `product-product`.

## Поточний список CSS на mobile product

На момент фіксації mobile product завантажує:

- Bootstrap;
- Magnific Popup;
- product zoom CSS;
- Swiper CSS;
- OpenCart Swiper CSS;
- Cloud Zoom CSS;
- swatches CSS;
- Form Builder CSS;
- Font Awesome;
- Ionicons;
- Plaza Icon;
- `stylesheet.css`;
- `header1.css`;
- `theme.css`.

Перед увімкненням загального defer кожен із цих CSS потрібно перевірити.

Частина з них може виявитися непотрібною і має бути видалена, а не просто відкладена.

## Робота з Coverage

Chrome Coverage використовується як фільтр для формування product-specific critical.

Не можна сліпо копіювати великі блоки CSS.

Правильна послідовність:

1. визначити вихідний локальний CSS-файл;
2. знайти точний source-блок;
3. перевірити використання правил через Coverage;
4. взяти лише правила першого екрана;
5. зберегти правильний cascade і порядок;
6. перевірити візуальний first render;
7. лише після цього відкладати повний CSS.

## Важливе зауваження щодо cascade

Загальний critical може містити широкі правила, які в повній темі пізніше перевизначаються page-specific CSS.

Після deferred завантаження повних CSS такі перевизначення мають бути вже в page-specific critical.

Особлива увага: `h1`, font-size, margins, product layout, responsive media rules, pseudo-elements іконок і правила з `!important`.

Не можна вважати, що більш специфічний селектор автоматично переможе загальне правило, якщо загальне правило містить `!important`.

## Модель викладання на сервер

Під час роботи з critical CSS зазвичай на сервер завантажується змінений OCMOD XML.

Локальні `critical-*.css` використовуються як вихідні файли для розробки та не зобов’язані бути на сервері.

Після зміни OCMOD:

1. завантажити XML;
2. виконати OCMOD Refresh;
3. очистити compiled Twig/cache за потреби;
4. перевірити live HTML;
5. перевірити візуальний рендер;
6. перевірити Console;
7. лише після цього перевіряти PageSpeed.

## Заплановане покращення

Потрібно зробити невеликий build/sync script, який автоматично вставлятиме вміст локальних critical CSS файлів у відповідні inline-блоки OCMOD.

Мета — усунути ручне дублювання й зробити локальні CSS-файли єдиним практичним source of truth.
