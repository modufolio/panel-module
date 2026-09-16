# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`PanelModule`**, moved here from `modufolio/panel`, which no longer
  depends on appkit. Same manifest entry, same `media_entity` key, same
  defaults; the class is now `Modufolio\PanelModule\PanelModule`.
- **`Security\TokenCurrentUser`** answers the panel's `CurrentUserInterface`
  from the kernel's token storage, and refuses a user class that lacks the
  panel's `UserInterface` by name rather than treating it as nobody.
- **`Http\InertiaPageRenderer`** answers the panel's `PageRendererInterface`
  with an appkit `Inertia` value for the kernel to finish.
- **`Routing\CachedResourceMenu::fromRouter()`**, formerly
  `ResourceMenu::fromRouter()` in the panel.
