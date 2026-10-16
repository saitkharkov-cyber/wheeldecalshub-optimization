# Wheeldecalshub — завершення проєкту

**Статус:** проєкт завершено та передано замовнику  
**Дата архівації:** 2026-10-16  
**Платформа:** OpenCart 3.0.3.2

## 1. Підсумок

Проєкт оптимізації Wheeldecalshub завершено. Основні роботи з продуктивності, критичного CSS, завантаження ресурсів, Nivo/Swiper, reCAPTCHA та стабілізації mobile/desktop сценаріїв виконані й зафіксовані в Git.

Репозиторій слід вважати основною точкою відновлення технічного контексту, якщо до проєкту доведеться повернутися через тривалий час.

## 2. Стабільний фінальний стан

### Mobile homepage

Репрезентативний стабільний baseline:

- Performance: ~99
- FCP: ~1.2 s
- LCP: ~2.0 s
- TBT: 0 ms
- SI: ~2.5 s
- CLS: ~0.05

Mobile homepage вважається стабільною. Не змінювати її без нової виміряної причини.

### Desktop homepage

Репрезентативний фінальний результат:

- Performance: 96–97
- FCP: ~0.7 s
- LCP: ~1.1 s
- TBT: ~50 ms
- CLS: ~0.001
- SI: ~1.2 s

Desktop homepage також вважається практично завершеною. Не робити агресивних оптимізацій без нового виміряного регресу.

## 3. Ключові інваріанти, які не можна випадково зламати

- Не редагувати `storage/modification` як джерело змін.
- Усі постійні зміни мають переживати OCMOD Refresh.
- Blocking jQuery на homepage залишено навмисно як стабільний виняток.
- Не повертати старий sequential delayed CSS loader.
- Не повертати стару Swiper lazy-логіку для mobile product module 206 без виміряної причини.
- Не використовувати `Mobile_Detect` у `catalog/controller/extension/captcha/google.php`.
- Після Twig-змін через OCMOD виконувати не тільки OCMOD Refresh, а й Theme Refresh / очищення compiled Twig cache.
- Critical CSS source-файли є source of truth; runtime/cache-файли — ні.

## 4. Відомі особливості та ризики

### Administrator permissions

Під час проєкту двічі спостерігалося широке скидання/перезапис прав групи Administrator без встановленого тригера.

Окремо було виявлено відсутність віртуального parent permission:

```text
plaza/blog
```

через що:

- Blog зникав з Plaza admin menu;
- прямий маршрут `plaza/blog/post` повертав `Permission Denied`.

Parent permission було точково відновлено в `access` і `modify` після резервного копіювання рядка `oc_user_group`.

Причина періодичного скидання прав Administrator залишається невстановленою. Якщо проблема повториться, розслідування починати з усіх місць, що змінюють `oc_user_group.permission`, а також з `editUserGroup`, `addPermission`, `removePermission`, installer/uninstaller логіки Plaza та сторонніх модулів.

### Plaza Blog

Не використовувати uninstall/reinstall Plaza Blog як спосіб ремонту permissions: uninstall видаляє таблиці блогу.

## 5. З чого починати, якщо до проєкту доведеться повернутися

Читати в такому порядку:

1. `docs/architecture/OPTIMIZATION_ARCHITECTURE.md`
2. `docs/architecture/CURRENT_PROJECT_STATE.md`
3. `docs/CRITICAL_CSS_ARCHITECTURE.md`
4. `docs/OCMOD_RULES.md`
5. `docs/AUDIT.md`
6. Git history — для точного відновлення послідовності змін

## 6. Основні документи архіву

- `docs/architecture/OPTIMIZATION_ARCHITECTURE.md`
- `docs/architecture/CURRENT_PROJECT_STATE.md`
- `docs/CRITICAL_CSS_ARCHITECTURE.md`
- `docs/OCMOD_RULES.md`
- `docs/AUDIT.md`
- `docs/TASK.md`
- `docs/history/LIGHTHOUSE_FULL_PAGE_CACHE_EXPERIMENT.md`

## 7. Робоче правило при майбутньому поверненні

Не намагатися відразу «ще трохи оптимізувати» сайт. Спочатку:

1. відновити контекст із документації та Git history;
2. перевірити поточний production state;
3. зняти новий baseline;
4. порівняти з фінальними метриками;
5. змінювати лише те, для чого є доведена причина.

Цей документ фіксує момент закриття проєкту й служить короткою точкою входу після тривалої паузи.
