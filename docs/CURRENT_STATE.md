# CURRENT STATE

Дата: **03.10.2026**

## Проєкт

Сайт: https://wheeldecalshub.com/  
CMS: OpenCart 3.0.3.2  
PHP: 7.3.33  
Тема: `tt_uren1`

## Baseline Mobile PageSpeed / Lighthouse

| Тип сторінки | Performance | FCP | LCP | TBT | CLS | SI |
|---|---:|---:|---:|---:|---:|---:|
| Головна | 44 | 6.8 s | 8.8 s | 0 ms | 0.285 | 6.8 s |
| Каталог | 35 | 7.4 s | 12.9 s | 0 ms | 0.704 | 7.4 s |
| Картка товару | 45 | 8.5 s | 9.6 s | 200 ms | 0.194 | 8.5 s |

Baseline-скриншоти збережено в `measurements/baseline/`.

## Виконаний аудит і підтверджені результати

### Каталог — CLS

Основну причину високого CLS підтверджено експериментально: товари сервером віддавалися як `product-list col-xs-12`, після чого `grid.js` перебудовував їх у grid. На mobile це спричиняло великий layout shift.

Тестова серверна видача одразу з класами:

`product-layout product-grid grid-style col-lg-3 col-md-3 col-sm-3 col-xs-6 product-items`

дала результат:

- baseline CLS: **0.704**;
- після server-side grid: **0.189**;
- Performance: **35 → 49**;
- LCP: **12.9 s → 12.0 s**.

Додатково виправлено резервування місця для зображень:

- Payment block: `payment.png` має реальний розмір **350×30**; у модулі `ptstaticblock.45` для обох мов додано `width="350" height="30"`;
- зображення товарів каталогу мають resize **600×600**; у `category.twig` додано `width="600" height="600"`.

Після server-side grid + Payment dimensions отримано контрольний PSI:

- Performance: **56**;
- FCP: **7.4 s**;
- LCP: **10.8 s**;
- TBT: **0 ms**;
- CLS: **0.094**;
- SI: **7.4 s**.

Повторні запуски показували коливання CLS приблизно **0.094–0.134**. Основну проблему CLS каталогу вважаємо локалізованою; далі пріоритет — FCP/LCP.

### Головна — slider

Підтверджено, що Nivo slider суттєво впливає на LCP/CLS. Тимчасове вимкнення slider дало приблизно:

- Performance **58–60**;
- FCP **6.6 s**;
- LCP **7.7–7.8 s**;
- CLS **0–0.082**.

Slider на mobile повинен залишитися, тому фінальне рішення має оптимізувати/полегшити його, а не видаляти.

## Поточні live-зміни каталогу

На сайті зараз залишені:

1. server-side initial grid у `catalog/view/theme/tt_uren1/template/product/category.twig`;
2. `width="600" height="600"` для product thumbs у `category.twig`;
3. `width="350" height="30"` для Payment image у `ptstaticblock.45` в обох мовах.

Перед фіналізацією потрібно окремо підтвердити поведінку AJAX-фільтра, localStorage/view switching та гарантоване збереження всіх файлових змін після OCMOD Refresh.

## OCMOD / Plaza Control Panel

Важливе уточнення структури:

- реальний generated cache знаходиться в `storage/modification/`, а не лише в `system/storage/modification/`;
- `storage/modification/catalog/controller/product/category.php` після OCMOD Refresh містить підключення:
  - `catalog/view/javascript/jquery/css/jquery-ui.css`;
  - `catalog/view/javascript/jquery/jquery-ui.js`;
- ці рядки додає модифікація **Plaza Control Panel 18.12.27**;
- фізичний `system/plaza_control_panel.ocmod.xml` не є джерелом, з якого поточний OCMOD Refresh відтворює цю модифікацію;
- у дампі БД знайдено повний XML модифікації `Plaza Control Panel` (`code=plaza_control_panel`), включно з CATEGORY CONFIGURATION та підключеннями jQuery UI;
- запис у дампі має status `0`, що відповідає Disabled у списку модифікацій, але generated cache після Refresh усе одно містить Plaza-код. Цю розбіжність треба дослідити перед постійними змінами.

Не редагувати `storage/modification/` як постійне рішення.

## jQuery UI / filter — поточна точка розслідування

Для категорії на initial load завантажуються:

- `jquery-ui.js` — приблизно **306,976 bytes**;
- `jquery-ui.css` — приблизно **36,437 bytes**.

jQuery UI функціонально потрібен Plaza price slider (`#slider-price`). На mobile сам filter/sidebar спочатку прихований і відкривається користувачем кнопкою, тому є потенціал прибрати jQuery UI з initial critical path і завантажувати його лише коли він реально потрібен.

Простий `defer` неприйнятний без переробки ініціалізації: `filter.twig` одразу викликає `$('#slider-price').slider(...)`.

Проведені хибні діагностичні правки повністю відкочені:

- `catalog/controller/plaza/filter.php` відновлено з backup;
- `system/plaza_control_panel.ocmod.xml` відновлено з backup.

## Theme / cache

Для змін Twig недостатньо OCMOD Refresh. Під час тестів підтверджено робочий цикл:

**upload Twig → Theme Refresh → перевірка raw HTML → PSI**.

OCMOD Refresh перевіряється окремо там, де потрібна гарантія persistence.

## Наступний крок

1. Визначити точну таблицю/структуру запису OCMOD modification у БД та чому Plaza Control Panel зі status=0 продовжує потрапляти в generated cache.
2. Безпечно провести короткий діагностичний тест без initial-load `jquery-ui.js` і `jquery-ui.css` саме через реальне джерело OCMOD.
3. OCMOD Refresh → перевірити, що ресурси справді зникли з live HTML.
4. Запустити Mobile PSI категорії та порівняти FCP/LCP з поточним станом.
5. Якщо виграш суттєвий — спроєктувати коректний lazy-load jQuery UI/filter зі збереженням price slider і AJAX filter.
6. Після цього оформити підтверджені зміни як постійні OCMOD-safe рішення та задокументувати їх у CHANGELOG.

## Правило

Усі фінальні оптимізації повинні переживати **OCMOD Refresh**. Generated-файли `storage/modification/` напряму не використовувати як джерело постійних змін.
