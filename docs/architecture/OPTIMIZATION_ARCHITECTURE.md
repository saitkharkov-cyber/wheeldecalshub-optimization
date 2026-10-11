# Архітектура оптимізації WheelDecalsHub

> **Перед зміною файлів теми, OCMOD, порядку завантаження CSS/JavaScript, mobile layout, обробки зображень або кешування обов'язково ознайомтеся з цим документом.** Частина стандартної поведінки OpenCart/Plaza навмисно змінена заради Core Web Vitals. Локально очевидне «спрощення» може повернути render blocking, CLS, зламати deferred loading або зробити OCMOD Refresh небезпечним.

## 1. Призначення документа

Цей документ описує не історію змін, а **поточну архітектуру оптимізації сайту WheelDecalsHub** і правила безпечної роботи з нею.

Головна мета — дати новому розробнику достатньо контексту, щоб він міг:

- зрозуміти, як працює оптимізація;
- відрізнити source files від generated/cache-файлів;
- не зламати Core Web Vitals випадковим поверненням legacy-поведінки;
- безпечно змінювати OCMOD, Twig, CSS, JavaScript, fonts та image delivery;
- правильно перевіряти результат після OCMOD Refresh;
- швидко локалізувати регресію.

**Мова документації проєкту — українська.** Імена файлів, шляхів, функцій, змінних, OCMOD, CSS/JS selectors, команд і фрагментів коду залишаються без перекладу.

---

## 2. Основний принцип роботи

У проєкті застосовується правило:

> **Один маленький безпечний крок за раз.**

Для кожної зміни:

1. знайти точну ціль;
2. порахувати точні match/count;
3. зробити backup;
4. виконати лише одну логічну зміну;
5. перевірити файл локально;
6. завантажити тільки потрібний файл;
7. виконати OCMOD Refresh, якщо зміна цього потребує;
8. перевірити generated-файл;
9. перевірити production DOM / Console;
10. запускати PSI лише після технічної валідації.

Не об'єднувати в один крок непов'язані зміни.

Для Windows використовується **PowerShell 5.1**. Не використовувати `&&`. Для нових file-based OCMOD XML використовувати UTF-8 **без BOM**.

Приклад запису без BOM:

```powershell
[System.IO.File]::WriteAllText($f,$s,(New-Object System.Text.UTF8Encoding($false)))
```

---

## 3. Mental model: як проходить зміна до браузера

Ключова модель проєкту:

```text
Physical source files
        ↓
OCMOD Refresh
        ↓
storage/modification
        ↓
PHP integration / WDH runtime library
        ↓
Twig compilation/cache
        ↓
Rendered HTML
        ↓
Browser critical/deferred loading
        ↓
PageSpeed / Core Web Vitals
```

Це означає, що одна і та сама зміна може існувати одночасно в кількох шарах.

### 3.1. Physical source files

Це джерело істини для коду теми й системних файлів, наприклад:

```text
catalog/view/theme/tt_uren1/...
catalog/controller/...
system/*.ocmod.xml
system/library/wdh/...
```

Постійні зміни робляться саме тут або через власні OCMOD.

### 3.2. `storage/modification`

Після OCMOD Refresh OpenCart генерує модифіковані копії файлів у:

```text
storage/modification/
```

Ці файли **не є source of truth**.

Не можна робити постійні правки безпосередньо в `storage/modification`, тому що наступний Refresh їх знищить.

### 3.3. Twig cache

Навіть якщо physical і generated Twig вже оновлені, production може продовжувати віддавати стару скомпільовану версію з:

```text
storage/cache/
```

Це вже траплялося під час додавання Work Sans preload: physical і generated `header_mobile.twig` містили новий `<link rel="preload">`, але rendered HTML його не містив, тому що використовувався старий Twig cache.

Висновок: при розбіжності між physical/generated і browser HTML перевіряти Twig cache.

---

## 4. OCMOD: правила безпечної роботи

OCMOD — центральний механізм постійних змін у проєкті.

### 4.1. Search-якорі є частиною архітектури

Не можна вважати рядок у Twig/PHP «непотрібним» лише тому, що сам по собі він більше нічого не робить. Він може використовуватися як `<search>`-якір іншого OCMOD.

Реальний приклад:

```js
$(".product-image-details").prepend($(".innermovemob"));
$("#product").prepend($(".innermovemobprice"));
```

Після переходу на server-side mobile layout ці два рядки стали функціонально непотрібними й були видалені з `product.twig`.

Але вони також були `<search>`-якорями в:

```text
system/wdh_mobile_deferred_js.ocmod.xml
```

Після їх видалення відповідні OCMOD operations перестали матчитися, wrapper `wdhWhenJQuery(...)` не застосувався, і на production з'явилася помилка:

```text
Uncaught ReferenceError: $ is not defined
```

Правильне рішення: видалити не лише legacy-рядки, а й відповідні obsolete OCMOD operations.

### 4.2. Перед видаленням рядка

Перед видаленням або значною зміною рядка в Twig/PHP/XML обов'язково пошукати його серед OCMOD:

```powershell
Get-ChildItem $r\system -Recurse -File -Include *.xml | Select-String -SimpleMatch 'TARGET_STRING'
```

### 4.3. Після Refresh

Перевіряти не тільки відсутність помилок у адмінці, а й generated output:

```text
storage/modification/...
```

Якщо OCMOD використовує точні `<search>`-рядки, навіть невелика зміна source-файлу може зробити operation неактивною.

---

## 5. Mobile detection: viewport ≠ mobile template

У темі Plaza mobile header вибирається **server-side** через `Mobile_Detect`, а не через CSS viewport.

Generated controller містить логіку на кшталт:

```php
$detect = new Mobile_Detect;

if ($detect->isTablet()) {
    return $this->load->view('common/header', $data);
} else {
    if ($detect->isMobile()) {
        return $this->load->view('plaza/page_section/header_mobile', $data);
    } else {
        return $this->load->view('common/header', $data);
    }
}
```

Наслідки:

- просто звузити desktop browser до 375 px недостатньо;
- server-side template визначається User-Agent;
- tablet навмисно поводиться як desktop;
- під час тестування mobile header потрібен mobile UA.

Це критично для перевірки preload, mobile critical CSS, deferred assets і template-specific змін.

---

## 6. Critical CSS architecture і PHP-бібліотека WDH

Critical CSS у проєкті не зберігається як велика inline-громада всередині OCMOD. Архітектура навмисно розділена на три шари:

```text
CSS source files
        ↓
system/library/wdh/critical_css.php
        ↓
system/wdh_critical_css_loader.ocmod.xml
        ↓
header controller / Twig
        ↓
inline <style> у rendered HTML
```

Це одна з ключових частин усієї оптимізації. Її не можна замінювати прямим копіюванням CSS назад у XML без окремого архітектурного рішення.

### 6.1. PHP-бібліотека `WdhCriticalCss`

Основний runtime loader:

```text
system/library/wdh/critical_css.php
```

У ньому визначений клас:

```php
class WdhCriticalCss
```

Бібліотека відповідає за:

- читання source CSS-файлів;
- складання common + route-specific critical CSS у правильному порядку;
- окрему збірку mobile та desktop critical CSS;
- кешування вже зібраного CSS;
- автоматичну перебудову кешу, якщо source CSS новіший за cache;
- fallback на останній валідний cache у частині failure-сценаріїв;
- повернення готового `<style>` для вставки в Twig.

Тобто OCMOD тут **не зберігає сам CSS**. OCMOD лише підключає PHP-бібліотеку та передає її результат у шаблон.

### 6.2. `read($file)`

Метод:

```php
public static function read($file)
```

повертає вміст файлу через `file_get_contents()` лише якщо файл існує. Якщо файлу немає — повертається порожній рядок.

Це базовий безпечний read-layer для решти бібліотеки.

### 6.3. Внутрішній cache critical CSS

Бібліотека використовує окремий runtime cache:

```text
DIR_STORAGE . 'cache/wdh_critical_css/'
```

Основні cache-файли:

```text
category-mobile.css
product-mobile.css
category-desktop.css
```

Метод:

```php
private static function getCachedCss($cacheName, $sources)
```

перевіряє `filemtime()` cache-файлу та всіх source-файлів.

Якщо хоча б один source CSS новіший за cache, bundle перебудовується автоматично.

Це означає: після звичайної зміни source CSS не треба вручну генерувати inline CSS у XML. Runtime library сама збере актуальний bundle.

Запис cache робиться через тимчасовий файл:

```text
<cacheFile>.tmp.<pid>
```

із `LOCK_EX`, після чого виконується `rename()` у фінальний cache-файл. Це зменшує ризик віддати користувачу частково записаний CSS при паралельних запитах.

### 6.4. Failure/fallback behavior

У бібліотеці навмисно є захисна поведінка.

Якщо під час rebuild один із очікуваних source-файлів раптово відсутній, але вже існує попередній cache, бібліотека може повернути старий cache замість порожнього critical CSS.

Якщо cache directory не вдалося створити, бібліотека все одно може повернути вже зібраний CSS поточного запиту або наявний cache.

Це важливо для production safety: тимчасова проблема файлової системи не повинна автоматично перетворювати сторінку на unstyled first paint.

Але цей fallback не можна сприймати як нормальний режим роботи. Якщо source-файл реально втрачено — потрібно виправити source, а не покладатися на старий cache.

### 6.5. `buildMobile($route)`

Mobile builder працює лише для:

```text
product/category
product/product
```

Для інших route він повертає порожній рядок.

Base directory:

```text
catalog/view/theme/tt_uren1/stylesheet/wdh/
```

Common source:

```text
critical-common-mobile.css
```

Для category додається:

```text
critical-category-mobile.css
```

і cache:

```text
category-mobile.css
```

Для product додається:

```text
critical-product-mobile.css
```

і cache:

```text
product-mobile.css
```

Для mobile category перед common CSS бібліотека також може додати:

```text
bestin.css
```

якщо файл існує.

Фінальний HTML повертається у вигляді:

```html
<style id="wdh-critical-mobile" media="(max-width:991px)">
...
</style>
```

Тобто mobile critical CSS route-aware і не повинен перетворюватися на один універсальний моноліт для всіх сторінок.

### 6.6. `buildDesktop($route)`

Desktop builder наразі працює лише для:

```text
product/category
```

Для інших route повертається порожній рядок.

Використовується source:

```text
critical-desktop.css
```

і cache:

```text
category-desktop.css
```

Фінальний HTML:

```html
<style id="wdh-critical-desktop" media="(min-width:992px)">
...
</style>
```

Tablet у поточній архітектурі належить до desktop-гілки.

### 6.7. OCMOD integrator

Інтеграцію PHP-бібліотеки виконує:

```text
system/wdh_critical_css_loader.ocmod.xml
```

Він модифікує:

```text
catalog/controller/common/header.php
```

і після отримання header scripts виконує приблизно таку логіку:

```php
require_once(DIR_SYSTEM . 'library/wdh/critical_css.php');
$wdh_route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
$data['wdh_critical_mobile_css'] = WdhCriticalCss::buildMobile($wdh_route);
$data['wdh_critical_desktop_css'] = WdhCriticalCss::buildDesktop($wdh_route);
```

Далі цей самий OCMOD додає output у Twig після:

```html
<base href="{{ base }}" />
```

Mobile header:

```text
catalog/view/theme/tt_uren1/template/plaza/page_section/header_mobile.twig
```

отримує:

```twig
{% if wdh_critical_mobile_css %}{{ wdh_critical_mobile_css|raw }}{% endif %}
```

Desktop header:

```text
catalog/view/theme/tt_uren1/template/common/header.twig
```

отримує:

```twig
{% if wdh_critical_desktop_css %}{{ wdh_critical_desktop_css|raw }}{% endif %}
```

### 6.8. Source of truth

Source of truth для critical CSS — файли в:

```text
catalog/view/theme/tt_uren1/stylesheet/wdh/
```

зокрема:

```text
critical-common-mobile.css
critical-category-mobile.css
critical-product-mobile.css
critical-desktop.css
bestin.css
```

Не повертатися до ручного паралельного редагування:

- CSS source-файлу;
- inline-копії того самого CSS всередині OCMOD/XML.

Саме PHP-бібліотека була введена, щоб прибрати цю дубльовану модель і винести великі стабільні CSS-блоки з XML у нормальні source-файли.

### 6.9. Як безпечно змінювати critical CSS

Нормальний workflow:

1. змінити потрібний source CSS у `stylesheet/wdh/`;
2. перевірити exact diff;
3. завантажити CSS на сервер;
4. переконатися, що його `mtime` новіший за відповідний WDH cache;
5. відкрити потрібний route — бібліотека перебудує cache автоматично;
6. перевірити rendered `<style id="wdh-critical-mobile">` або `<style id="wdh-critical-desktop">`;
7. перевірити first render / CLS / PSI.

OCMOD Refresh для самої зміни CSS source зазвичай не потрібен, якщо не змінювалася інтеграційна логіка або Twig/controller.

Якщо є підозра на stale WDH cache, спочатку локалізувати конкретний файл у:

```text
storage/cache/wdh_critical_css/
```

і видаляти лише потрібний bundle, а не весь cache сайту.

### 6.10. Що не можна робити з PHP-бібліотекою

Без окремого рішення не можна:

- видаляти `system/library/wdh/critical_css.php` як «зайвий проміжний шар»;
- переносити великі CSS-блоки назад у `wdh_critical_css_loader.ocmod.xml`;
- дублювати один і той самий critical CSS одночасно в PHP, XML і CSS source;
- міняти порядок `bestin.css → common → route-specific` без візуальної та PSI-перевірки;
- міняти media boundaries `991/992` без перевірки tablet behavior;
- редагувати `storage/cache/wdh_critical_css/*.css` як source;
- вважати cached bundle джерелом істини.

---

## 7. Deferred CSS

На mobile product/category частина великих стилів відкладена, щоб прибрати render blocking.

Серед deferred ресурсів можуть бути:

```text
stylesheet.css
plaza/theme.css
zoom/swiper/opencart/cloud-zoom/swatches CSS
```

Critical CSS повинен містити мінімально необхідну геометрію першого екрану, щоб deferred CSS не спричиняв FOUC/CLS.

Не можна просто повернути всі CSS у блокуючий `<head>` «щоб нічого не мигало» — це поверне Render-blocking requests і погіршить FCP/LCP.

---

## 8. Deferred JavaScript і jQuery

Legacy Plaza-код містить багато inline jQuery, який історично розраховував на глобальний `$` ще під час парсингу HTML.

Для Core Web Vitals jQuery завантажується відкладено, тому залежні блоки не можна виконувати напряму.

Використовується wrapper:

```js
wdhWhenJQuery(function($){
    // legacy jQuery code
});
```

Основний OCMOD:

```text
system/wdh_mobile_deferred_js.ocmod.xml
```

### 8.1. Не робити

Не можна:

- повертати blocking jQuery в `<head>` без доказаної необхідності;
- видаляти `wdhWhenJQuery(...)` лише для усунення локальної помилки;
- змінювати OCMOD search-якір без перевірки відповідної operation;
- припускати, що `$(...)` без wrapper безпечний на mobile.

### 8.2. Типова помилка

```text
Uncaught ReferenceError: $ is not defined
```

Перевірити:

1. чи завантажується jQuery з `defer`;
2. чи обгорнутий конкретний inline block у `wdhWhenJQuery`;
3. чи спрацював OCMOD search;
4. чи не був видалений search-якір;
5. generated Twig після Refresh.

---

## 9. Product page: стабільна архітектура

Mobile product page наразі є стабільним baseline і не повинна повторно «оптимізуватися» без реальної регресії.

Фінальний mobile PSI checkpoint після cleanup:

```text
Performance: 100
FCP: 0.9 s
LCP: 1.4 s
TBT: 0 ms
Speed Index: 1.9 s
CLS: 0.018
```

Окремий контроль після font/preload fix давав CLS = 0.

### 9.1. Product CLS: root cause

Раніше mobile HTML рендерився в desktop-порядку, а потім jQuery переміщував:

```js
$(".product-image-details").prepend($(".innermovemob"));
$("#product").prepend($(".innermovemobprice"));
```

Під Lighthouse CPU throttling DOM move відбувався після першого paint і зсував `.product-zoom-image`.

### 9.2. Permanent solution

Використовується server-side flag:

```text
system/wdh_product_mobile_layout.ocmod.xml
```

Він додає `$data['wdh_mobile']` у:

```text
catalog/controller/product/product.php
```

Mobile Twig одразу рендерить правильний DOM-порядок.

Tablet залишається desktop.

Не повертати client-side DOM relocation.

### 9.3. Product critical CLS rule

У:

```text
catalog/view/theme/tt_uren1/stylesheet/wdh/critical-product-mobile.css
```

залишити:

```css
#product-product .product-zoom-image{aspect-ratio:1/1;}
```

### 9.4. Product image delivery

Активний OCMOD:

```text
system/wdh_product_mobile_lcp_image.ocmod.xml
```

Mobile main image віддається приблизно 480×480 WebP, desktop/tablet зберігають власну resize-логіку.

Main product image має:

```html
fetchpriority="high"
```

Option images мають явні:

```html
width="50" height="50"
```

### 9.5. Важливе застереження щодо Cloud Zoom

Не додавати main product `<img width/height>` із `thumb_dimension`.

Такий експеримент уже робився й ламав Cloud Zoom, бо `thumb_dimension` містив розміри близько 800×800, тоді як реальний mobile layout був близько 410×410.

---

## 10. Category page

Category optimization включає:

- critical CSS для mobile;
- стабілізацію image geometry;
- пріоритет першого LCP product image;
- зменшення CLS;
- підготовку до responsive image delivery.

Для LCP використовується власна OCMOD-логіка, зокрема:

```text
system/wdh_category_lcp_priority.ocmod.xml
```

Історично CLS category було знижено приблизно з 0.7 до близько 0.09.

Не змінювати category image dimensions або LCP priority без повторного PSI/DOM validation.

---

## 11. Homepage

Homepage optimization ще не завершена.

Основний наступний великий етап — оптимізація головної сторінки, включно зі складним slider.

До завершення цього етапу не вважати homepage architecture стабільною.

Усі нові стабільні рішення по slider, homepage LCP, critical assets і CLS повинні бути додані в цей документ після завершення відповідного етапу.

---

## 12. Fonts

Google Fonts були прибрані.

Основний текстовий font:

```text
catalog/view/theme/tt_uren1/fonts/worksans/WorkSans-v24-latin.woff2
```

Critical `@font-face`:

```css
@font-face{
  font-family:"Work Sans";
  src:url("catalog/view/theme/tt_uren1/fonts/worksans/WorkSans-v24-latin.woff2") format("woff2");
  font-weight:100 900;
  font-style:normal;
  font-display:swap;
}
```

### 12.1. Mobile Work Sans preload

Preload додається в:

```text
catalog/view/theme/tt_uren1/template/plaza/page_section/header_mobile.twig
```

Важливо: relative preload URL має бути **після**:

```html
<base href="{{ base }}" />
```

Правильний порядок:

```html
<title>{{ title }}</title>
<base href="{{ base }}" />
<link rel="preload" href="catalog/view/theme/tt_uren1/fonts/worksans/WorkSans-v24-latin.woff2" as="font" type="font/woff2" crossorigin>
```

### 12.2. Чому це важливо

Коли preload стояв до `<base>`, браузер резолвив relative URL від поточного deep product URL і робив зайвий 404, а потім CSS окремо завантажував правильний font.

Тобто в Network було два font request:

- неправильний preload → 404;
- правильний CSS request → 200.

Після перенесення preload після `<base>` проблема зникла.

### 12.3. Cache

WOFF2 має довгий browser cache (`max-age` приблизно рік). Не змінювати cache policy без причини.

### 12.4. Bestin

Bestin використовує окремий ранній inline/base64 підхід. Не переносити цей патерн автоматично на Work Sans: Work Sans значно більший, і base64 роздув би initial HTML.

---

## 13. Cache layers і troubleshooting

У проєкті треба розрізняти кілька типів cache:

- browser cache;
- OpenCart cache;
- OCMOD generated files;
- Twig compiled cache;
- WDH critical CSS runtime cache (`storage/cache/wdh_critical_css/`).

### 13.1. Physical є, generated є, browser не бачить

Ймовірна причина — stale Twig cache.

Алгоритм:

1. перевірити physical file;
2. перевірити `storage/modification/...`;
3. знайти cached Twig-файл через marker/template path;
4. перевірити його вміст;
5. видалити лише конкретний stale cache file;
6. перезавантажити production.

Не очищати весь cache без необхідності.

### 13.2. Critical CSS не оновився після зміни source

Перевірити:

1. source-файл у `stylesheet/wdh/`;
2. `filemtime()` source і відповідного cache bundle;
3. файл у `storage/cache/wdh_critical_css/`;
4. rendered `<style id="wdh-critical-mobile">` / `<style id="wdh-critical-desktop">`.

Якщо потрібно примусово перебудувати bundle, видалити лише відповідний WDH cache-файл і повторно відкрити route.

---

## 14. Safe debugging workflow

### 14.1. `$ is not defined`

Перевіряти deferred jQuery wrapper і OCMOD anchors.

### 14.2. OCMOD operation не працює

Перевірити exact `<search>` у physical source і generated file.

### 14.3. Зміна є у physical, але її немає на сайті

Перевірити:

```text
physical → storage/modification → Twig cache → rendered HTML
```

Для critical CSS окремо враховувати runtime layer:

```text
source CSS → WdhCriticalCss → storage/cache/wdh_critical_css → rendered <style>
```

### 14.4. Mobile template не використовується

Перевірити User-Agent, а не лише viewport width.

### 14.5. Preload не працює

Перевірити:

- чи присутній `<link rel="preload">` у rendered DOM;
- resolved `href`;
- положення відносно `<base>`;
- Network status;
- duplicate requests;
- stale Twig cache.

### 14.6. CLS раптово повернувся

Перевірити:

- client-side DOM moves;
- image geometry;
- critical CSS reservation;
- deferred CSS arrival;
- font swap;
- slider/carousel initialization.

---

## 15. Danger zones — що не робити

Без окремої перевірки не можна:

- редагувати `storage/modification` як permanent source;
- повертати blocking jQuery;
- прибирати `wdhWhenJQuery` wrappers;
- змінювати OCMOD search-якорі без пошуку залежних operations;
- видаляти або обходити `system/library/wdh/critical_css.php`;
- переносити critical CSS назад у великі inline XML-блоки;
- редагувати `storage/cache/wdh_critical_css/*.css` як source;
- повертати mobile DOM relocation через jQuery;
- додавати main product image `width/height` із `thumb_dimension`;
- реактивувати окремий `product_mobile.twig`;
- змішувати mobile і desktop critical CSS;
- вручну підтримувати дві незалежні копії однакового critical CSS;
- ставити relative preload перед `<base>`;
- очищати весь Twig/OpenCart cache без локалізації проблеми;
- вважати narrow desktop viewport еквівалентом mobile server-side render.

---

## 16. Known technical debt

Поточна другорядна черга:

- cleanup Magnific Popup / legacy product scripts;
- cleanup datetime/upload logic;
- спрощення deferred JS architecture;
- подальший responsive image / `srcset` / WebP pipeline;
- поступове зменшення legacy OCMOD моноліту;
- розслідування окремого інциденту з admin permissions, якщо він повториться.

Critical CSS source-of-truth уже реалізований через PHP-бібліотеку `WdhCriticalCss`; окремий sync-script для дублювання CSS у XML більше не є цільовою архітектурою.

Ці задачі не повинні виконуватися шляхом повернення blocking assets або руйнування вже стабільних Core Web Vitals.

---

## 17. Stable checkpoints

### Mobile product

Перевірений стабільний результат:

```text
Performance: 100
FCP: 0.9 s
LCP: 1.4 s
TBT: 0 ms
Speed Index: 1.9 s
CLS: 0.018
```

Після окремого font/preload fix PSI також показував:

```text
CLS: 0
```

Ці значення не є абсолютною гарантією для кожного запуску PSI, але є baseline для виявлення явних регресій.

---

## 18. Перед комітом

Перед кожним performance-related commit:

- перевірити `git diff`;
- переконатися, що в commit немає backup-файлів;
- перевірити OCMOD XML на валідність;
- перевірити UTF-8 без BOM для нових OCMOD XML;
- зробити OCMOD Refresh, якщо змінювалась OCMOD/integration-логіка;
- перевірити generated files;
- для critical CSS перевірити WDH runtime bundle;
- перевірити Console;
- перевірити ключовий DOM;
- при зміні first-render behavior — зробити mobile PSI.

---

## 19. Підсумок

Оптимізація WheelDecalsHub побудована не як набір випадкових patch-ів, а як система з кількох шарів:

```text
OpenCart / Plaza
+ OCMOD
+ WDH PHP runtime library
+ server-side mobile branching
+ critical CSS
+ deferred CSS/JS
+ controlled image delivery
+ local fonts/preload
+ cache-aware validation
```

Головний принцип підтримки цієї системи:

> **Не змінювати порядок завантаження, DOM structure, PHP integration або OCMOD anchors, не розуміючи, який шар оптимізації від них залежить.**

Якщо нова зміна «виправляє» локальну помилку шляхом повернення старої blocking/legacy поведінки або обходу `WdhCriticalCss`, її потрібно вважати підозрілою до повної перевірки Core Web Vitals і generated output.