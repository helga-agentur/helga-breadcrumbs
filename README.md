## INTRODUCTION

This module provides a generic strategy for generating breadcrumbs.
It relies on [MenuBreadcrumbBuilder](https://www.drupal.org/project/menu_breadcrumb/), some custom fallback catch-all logic and
[EasyBreadcrumbBuilder](https://www.drupal.org/project/easy_breadcrumb) to achieve the following:

1. Generate a breadcrumb for the current entity based on its placement in a globally defined menu
2. If there is no menu item for the current entity, fallback to a fallback
   menu item for 'orphans', defined per entity type
3. If that doesn't work either, fallback to EasyBreadcrumb's logic
4. If EasyBreadcrumb cannot be applied, use any other available system breadcrumb builder
   * N.B.: This depends on the priority of `breadcrumb_builder` tagged services.

![Flow](./flow_file.svg)
(created with [mermaidchart.com](https://www.mermaidchart.com/app/projects/676379aa-f3c3-42c4-acb6-34c34a9e0d0c/diagrams/53175503-0b59-49a8-a1ac-0f31459ceb7e/share/invite/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkb2N1bWVudElEIjoiNTMxNzU1MDMtMGI1OS00OWE4LWExYWMtMGYzMTQ1OWNlYjdlIiwiYWNjZXNzIjoiRWRpdCIsImlhdCI6MTc1Mzg0OTQ0OX0._fxlahj-BHTY24PXK5U5b3PLXhYcecYYcJe06kVtE2Q))

## REQUIREMENTS

* Easy Breadcrumb
* Menu Breadcrumb

## CONFIGURATION

### Helga breadcrumbs

Define the menu used to generate breadcrumbs,
via `helga_breadcrumbs.settings breadcrumbs_orphans_menu`
Having defined that, make sure you add fallback "orphan" parents menu items for
node and other entity types, as needed.

### Menu breadcrumbs

By setting `determine_menu` to `true` one can completely disable this builder,
while still being able to use the module's codes for the 'orphans' fallback
logic.

### Easy breadcrumbs

Some suggested settings:

* `remove_repeated_segments: true`
* `applies_admin_routes: false`
* `include_title_segment: false`
* `follow_redirects: false`

## NOTES

EasyBreadcrumb may have an issue with aliases generated using the following token:

```
[node:menu-link:parents:join-path]
```

In order to fix this issue (tracked at https://www.drupal.org/i/2952612), a patch is needed (provided via that issue)
and some custom implementation.
