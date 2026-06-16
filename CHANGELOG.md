## [2.0.2] - 2026-06-16
### Changed
- Builder now uses the detected WordPress `Plugin Name` header as the WP Desk product value instead of asking integrators for a separate product ID.
- Licensed as MIT.

## [2.0.1] - 2026-05-29
### Changed
- Removed WooCommerce excludes library from project as no WooCommerce functions are used.

## [2.0.0] - 2026-05-28
### Added
- Interactive and automated PHP-based post-project creation builder (`BuilderScript`) replacing the legacy bash/manual setup scripts.
- Automatic main plugin file detection and plugin name header extraction.
- Automatic PHP namespace prefix detection from the target plugin's `composer.json` (PSR-4) or fallback generation from the folder slug.
- Automated cleanup of build files, configurations, and dev dependencies, leaving only the scoped production bundle in the target directory.

### Changed
- Refactored `ExternalIntegration::integrate()` signature to accept only a single `$plugin_file` path alongside the product ID. Dynamic metadata resolution (such as version, plugin name, and slug) is now handled automatically via standard WordPress APIs.
- Updated minimum PHP requirement to `>= 7.4`.
- Replaced the manual three-step interactive prompt with a single streamlined WP Desk product ID question (pre-filled with the detected plugin name as default).

### Removed
- Legacy manual configuration files: `composer-integration.json`, `scoper.inc.php`, and root `wpdesk-integration.php`.

## [1.3.1] - 2023-06-05
### Fixed
- Start license system after plugins loaded

## [1.3.0] - 2023-06-05
### Changed
- Plugin flow ^3
- New license system

## [1.2.1] - 2020-07-10
### Fixed
- Error in update request
- Error in plugin option storage
### Changed
- Library update

## [1.1.0] - 2020-04-23
### Added
- Composer 2 support
### Changed
- Library update

## [1.0.0] - 2020-01-27
### Added
- First version.
