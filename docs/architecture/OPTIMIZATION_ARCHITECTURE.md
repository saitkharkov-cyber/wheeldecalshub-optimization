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

## 6. Critical CSS architecture

Critical CSS фізично розділено за типом пристрою й route.

Основний loader:

```text
system/library/wdh/critical_css.php
```

Ключові функції:

```php
buildMobile($route)
buildDesktop($route)
```

Integrator:

```text
system/wdh_critical_css_loader.ocmod.xml
```

### 6.1. Mobile

Mobile critical CSS складається з окремих файлів, зокрема:

```text
catalog/view/theme/tt_uren1/stylesheet/wdh/critical-common-mobile.css
catalog/view/theme/tt_uren1/stylesheet/wdh/critical-category-mobile.css
catalog/view/theme/tt_uren1/stylesheet/wdh/critical-product-mobile.css
```

### 6.2. Desktop/tablet

Tablet згрупований з desktop.

Desktop critical CSS підключається окремо й не повинен змішуватися з mobile critical CSS.

### 6.3. Source of truth

Напрямок архітектури — **CSS-файли мають бути source of truth**.

Не повертатися до ручного паралельного редагування:

- CSS-файлу;
- inline-копії того самого CSS всередині OCMOD.

Для legacy-ділянок допускається тимчасова сумісність, але майбутня ціль — генерація inline critical CSS зі source CSS автоматично.

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
- Twig compiled cache.

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
- `sync-critical-css.ps1` для єдиного source of truth;
- подальший responsive image / `srcset` / WebP pipeline;
- поступове зменшення legacy OCMOD моноліту;
- розслідування окремого інциденту з admin permissions, якщо він повториться.

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
- зробити OCMOD Refresh;
- перевірити generated files;
- перевірити Console;
- перевірити ключовий DOM;
- при зміні first-render behavior — зробити mobile PSI.

---

## 19. Підсумок

Оптимізація WheelDecalsHub побудована не як набір випадкових patch-ів, а як система з кількох шарів:

```text
OpenCart / Plaza
+ OCMOD
+ server-side mobile branching
+ critical CSS
+ deferred CSS/JS
+ controlled image delivery
+ local fonts/preload
+ cache-aware validation
```

Головний принцип підтримки цієї системи:

> **Не змінювати порядок завантаження, DOM structure або OCMOD anchors, не розуміючи, який шар оптимізації від них залежить.**

Якщо нова зміна «виправляє» локальну помилку шляхом повернення старої blocking/legacy поведінки, її потрібно вважати підозрілою до повної перевірки Core Web Vitals і generated output.
