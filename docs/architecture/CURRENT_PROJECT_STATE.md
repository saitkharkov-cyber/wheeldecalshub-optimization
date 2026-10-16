# Wheeldecalshub — Current Project State

**Project:** Wheeldecalshub  
**Platform:** OpenCart 3.0.3.2  
**Repository:** `D:\Git\wheeldecalshub-optimization`  
**Full local site copy:** `D:\work\WDH-files\`  
**Branch:** `main`

## 1. Purpose of this document

This is the permanent recovery/handoff document for the Wheeldecalshub optimization project.

Unlike the temporary `CURRENT_STATE.md` files used during active work, this document is intended to remain in Git and contain only durable project knowledge: architecture, invariants, known exceptions, recovery notes, and unresolved risks.

The repository is the source of history/documentation. Production deployment is performed manually unless explicitly stated otherwise.

## 2. Working rules

Work in small, reversible steps:

1. Inspect/search first.
2. Confirm exact match/count.
3. Back up before editing.
4. Make one minimal change.
5. Verify syntax/content.
6. Deploy manually.
7. Refresh the relevant OpenCart caches/modifications.
8. Verify actual HTML/Network/runtime behavior.
9. Retest PSI/Lighthouse only after runtime verification.

Do not edit `storage/modification` as a source file. Permanent changes must survive OCMOD Refresh.

Historically shared PowerShell commands were kept compatible with Windows PowerShell 5.1: avoid `&&`; prefer `;` or explicit `$LASTEXITCODE` checks.

## 3. Optimization architecture

Optimization is page-type and device-specific.

Page types:
- homepage
- category
- product

Device classes:
- mobile
- desktop/tablet

General principle:

**measure → identify proven cause → make minimal change → retest**

Do not apply broad performance changes without a measured cause.

## 4. Critical CSS architecture

Primary source files:

- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-common-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-home-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-category-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-product-mobile.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-desktop.css`
- `catalog/view/theme/tt_uren1/stylesheet/wdh/critical-home-desktop.css`

Runtime:
- `system/library/wdh/critical_css.php`
- `system/wdh_critical_css_loader.ocmod.xml`

Runtime cache:
- `DIR_STORAGE . 'cache/wdh_critical_css/'`

Important rule: **critical CSS source files are the source of truth; generated runtime/cache files are not.**

Desktop builder behavior:
- `product/category` → `category-desktop.css`
- `common/home` → `home-desktop.css`

Homepage desktop critical CSS includes:

```css
.common-home .plaza-slider{aspect-ratio:1770/617;}
```

This reserves Nivo slider height and is required to keep homepage desktop CLS near zero.

## 5. Deferred CSS

Deferred stylesheets are stored in:

```html
<template class="wdh-deferred-css">
```

Current architecture:
- batch preload after `load`
- apply after interaction

Do not restore the old sequential delayed CSS loader.

## 6. Homepage jQuery exception

Homepage blocking jQuery is an intentional stable exception.

Do not defer/remove it without a new measured reason. Earlier experiments showed regressions in Plaza/Nivo behavior.

## 7. Mobile homepage invariants

The mobile homepage is considered stable and should not be modified without a measured regression.

Representative stable baseline:
- Performance: ~99
- FCP: ~1.2 s
- LCP: ~2.0 s
- TBT: 0 ms
- SI: ~2.5 s
- CLS: ~0.05

Important Nivo rule:

```css
@media (max-width:767px){
  .common-home.mobile-layout .plaza-slider img{
    min-height:0;
    height:auto;
  }
}
```

Do not remove or override this accidentally.

Mobile slider images are approximately `435×151` WebP.

Form Builder is disabled on mobile homepage, but registration works independently.

## 8. Desktop homepage final state

Desktop homepage optimization is practically complete.

Representative final measurements:
- Performance: 96–97
- FCP: ~0.7 s
- LCP: ~1.1 s
- TBT: ~50 ms
- CLS: ~0.001
- SI: ~1.2 s

Do not make aggressive homepage changes unless a new measured issue appears.

### Desktop CLS fix

The main CLS source was the Nivo slider because desktop had no reserved height.

Fix:

```css
.common-home .plaza-slider{aspect-ratio:1770/617;}
```

### Nivo LCP fix

Original initialization used:

```js
$(window).load(function(){ ... })
```

Changed to:

```js
$(function(){ ... })
```

This materially improved LCP. Keep this change.

### Module 206 / Swiper

Desktop module 206 Swiper initialization was deferred with `IntersectionObserver`, approximately:

```js
rootMargin: 200px 0px
```

Mobile image loading remains separate. Do not reintroduce old Swiper lazy behavior for the mobile product module.

## 9. reCAPTCHA architecture

Relevant files:
- `catalog/controller/extension/captcha/google.php`
- `system/wdh_product_recaptcha_lazy.ocmod.xml`

### Important failure history

A previous implementation instantiated `Mobile_Detect` inside the captcha controller and caused:

```text
Class 'Mobile_Detect' not found
```

on `/information-contact`.

**Never reintroduce `Mobile_Detect` into the captcha controller.**

Current route fallback:

```php
$data['route'] = isset($this->request->get['route'])
    ? $this->request->get['route']
    : 'common/home';
```

The fallback matters because `/` may not contain an explicit route.

### Current lazy-load behavior

`wdh_product_recaptcha_lazy.ocmod.xml`:
- product page → load captcha on review interaction/hash
- homepage desktop → load captcha only on Register click
- homepage mobile → load captcha immediately when body has `mobile-layout`
- other pages → original direct Google script

### Twig cache rule

After modifying Twig via OCMOD, OCMOD Refresh alone is not enough.

Required workflow:
1. upload XML
2. OCMOD Refresh
3. Theme Refresh / clear compiled Twig cache
4. hard reload
5. inspect actual HTML/Network/resources
6. run PSI/Lighthouse

A stale Twig cache can make production continue executing old markup even when `storage/modification` already contains the new Twig.

## 10. OCMOD facts

OpenCart modification refresh scans:

```php
glob(DIR_SYSTEM . '*.ocmod.xml')
```

Therefore `system/*.ocmod.xml` participates in OCMOD Refresh even without an `oc_modification` DB entry.

Do not infer that an OCMOD is inactive merely because it is absent from `oc_modification`.

Do not inspect local `storage/modification` after refreshing production and assume it reflects the server.

Production storage:

```text
/var/www/alex7529425gma/data/www/storage
```

Production web root:

```text
/var/www/alex7529425gma/data/www/wheeldecalshub.com
```

## 11. Plaza Blog and Administrator permissions

The site contains at least two blog systems, but the visible historical articles are handled by **Plaza Blog**, not D Blog Module.

Relevant admin controllers:
- `admin/controller/plaza/blog/post.php`
- `admin/controller/plaza/blog/list.php`
- `admin/controller/plaza/blog/setting.php`

Relevant model:
- `admin/model/plaza/blog.php`

Relevant control panel:
- `admin/controller/extension/module/ptcontrolpanel.php`

### OpenCart permission behavior

`admin/controller/startup/permission.php` builds the permission route from only the first two route segments for non-extension routes.

Therefore:

```text
plaza/blog/post
```

is initially checked as:

```text
plaza/blog
```

before the child controller executes.

Plaza also uses the virtual parent permission:

```php
$this->user->hasPermission('access', 'plaza/blog')
```

to decide whether to render the Blog menu.

There is no physical:

```text
admin/controller/plaza/blog.php
```

The parent `plaza/blog` permission is effectively a virtual grouping permission.

### October 2026 incident

Symptoms:
- Blog disappeared from Plaza admin menu.
- Direct route `plaza/blog/post` returned `Permission Denied`.
- Child permissions still existed:
  - `plaza/blog/post`
  - `plaza/blog/list`
  - `plaza/blog/setting`
- Missing parent:
  - `plaza/blog`

Live DB verification showed:

```text
access plaza/blog/post    = 1
access plaza/blog/list    = 1
access plaza/blog/setting = 1
access plaza/blog         = 0
```

Before repair a backup table was created:

```sql
CREATE TABLE oc_user_group_wdh_backup_20261015 AS
SELECT * FROM oc_user_group
WHERE user_group_id = 1;
```

Fix: add only:

```text
access -> plaza/blog
modify -> plaza/blog
```

After restoration:
- JSON remained valid
- `access_blog = 1`
- `modify_blog = 1`
- Blog menu and posts returned immediately
- Existing articles were intact

## 12. Unresolved Administrator permission issue

During the project there were **two separate incidents where Administrator permissions were broadly reset/overwritten without an identified trigger**.

This remains unresolved.

Do not assume a specific cause until reproduced.

Future investigation should focus on:
- writes to `oc_user_group.permission`
- admin User Group save actions
- third-party extension install/uninstall paths
- Plaza install/uninstall routines
- event/OCMOD paths calling `editUserGroup`, `addPermission`, or `removePermission`

The recent missing `plaza/blog` parent permission may be related, but this is not proven.

Do not tell a client that optimization caused the permission resets unless evidence is found.

## 13. Plaza Blog installer behavior

`admin/model/plaza/blog.php` install code explicitly adds:
- `plaza/blog`
- `plaza/blog/post`
- `plaza/blog/list`
- `plaza/blog/setting`

for both `access` and `modify`.

Its uninstall routine removes the same permissions and drops blog tables.

**Do not run Blog uninstall/reinstall merely to restore a permission**, because uninstall drops blog tables.

## 14. Form Builder vs Plaza registration

Do not confuse OCdevWizard Form Builder with Plaza registration.

Homepage `g-recaptcha` was traced to hidden Plaza registration form `ajax-register-form`, loaded through:

```text
catalog/controller/extension/module/ptajaxlogin.php
```

which loads:

```text
plaza/register
```

Form Builder was unrelated to that captcha path.

## 15. Product/category notes

Category CLS was previously reduced substantially by image-dimension and related fixes.

The first category LCP product image receives high fetch priority through:

```text
system/wdh_category_lcp_priority.ocmod.xml
```

Product module 206 mobile images intentionally have no initial `src` and use an interaction loader.

Do not restore old Swiper lazy behavior without measurement.

## 16. Operational cautions

Do not:
- edit generated `storage/modification` files as source
- remove homepage blocking jQuery casually
- restore old sequential CSS deferral
- reintroduce `Mobile_Detect` in captcha controller
- confuse Form Builder with Plaza registration
- assume absence from `oc_modification` means an OCMOD is inactive
- assume local storage reflects production storage
- reinstall Plaza Blog merely to restore a permission
- treat temporary `CURRENT_STATE.md` as permanent Git history

## 17. Git and project state

Permanent project documentation belongs in Git. Temporary active-session state does not.

Before deleting old project chats, make sure:
- Git working tree is clean
- `main` matches `origin/main`
- this document is committed and pushed
- `OPTIMIZATION_ARCHITECTURE.md` is current
- runtime files changed in production are mirrored in repository history

At the time this document was prepared:

```text
## main...origin/main
```

with no uncommitted files.

## 18. Primary documentation

Key permanent documents:
- `docs/architecture/OPTIMIZATION_ARCHITECTURE.md`
- `docs/CRITICAL_CSS_ARCHITECTURE.md`
- `docs/OCMOD_RULES.md`
- `docs/AUDIT.md`
- `docs/TASK.md`
- `docs/history/LIGHTHOUSE_FULL_PAGE_CACHE_EXPERIMENT.md`

This file supplements them with recovery-oriented operational knowledge and known anomalies.

## 19. Recovery reading order

If a future agent/developer has no chat history:

1. `docs/architecture/OPTIMIZATION_ARCHITECTURE.md`
2. this document
3. `docs/CRITICAL_CSS_ARCHITECTURE.md`
4. `docs/OCMOD_RULES.md`
5. `docs/AUDIT.md`
6. Git history for exact implementation changes

With these documents and repository history, old ChatGPT conversations should not be required for routine continuation of the project.
