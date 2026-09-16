# modufolio/panel-module

[![CI](https://img.shields.io/github/actions/workflow/status/modufolio/panel-module/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/modufolio/panel-module/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Packagist](https://img.shields.io/packagist/v/modufolio/panel-module?style=flat-square)](https://packagist.org/packages/modufolio/panel-module)
[![License: MIT](https://img.shields.io/badge/License-MIT-brightgreen.svg?style=flat-square)](https://opensource.org/licenses/MIT)

The appkit module for [modufolio/panel](https://github.com/modufolio/panel).
The panel depends on no framework; this package is where appkit answers what
it asks of a host.

```php
// config/modules.php
return [
    \Modufolio\Appkit\Inertia\InertiaModule::class => ['version_file' => __DIR__ . '/sri.php'],
    \Modufolio\PanelModule\PanelModule::class => ['media_entity' => \App\Entity\Media::class],
];
```

The module wires the panel's `ResourceController` and `PanelController` from
the kernel's container, and declares:

- `Contracts\CurrentUserInterface` — the signed-in user off the kernel's token
  storage. The host's user class adds `Modufolio\Panel\Contracts\UserInterface`;
  its `getRoles()` already satisfies it.
- `Contracts\PageRendererInterface` — a panel page as an appkit Inertia value,
  which the kernel finishes through the host's `InertiaModule`.
- `FormResolver` naming the configured media entity, `GlobalSearch` over the
  mounted resources, a `NullChangePublisher`, a no-formats export provider and
  no permission report — defaults the host overrides by declaring the same id
  in `config/services.php`.

`Routing\CachedResourceMenu::fromRouter()` reads the generated menu entries
through appkit's route-data cache, for a host navigation that would otherwise
load every route from source on each request.

Configuration: `media_entity`, the entity class of the media library, so a
to-one pointing at it is guessed as an image field; `null` for no library.
