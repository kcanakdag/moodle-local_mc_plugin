# Changelog

All notable changes to the MoodleConnect plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [4.0.3] - 2025-12-15

### Fixed
- **ESLint Complexity**: Further refactored `connect.js` init function by extracting `getConfigValue()` and `buildConfig()` helpers.
- **PHPCS Lang Ordering**: Fixed alphabetical ordering of language strings in `lang/en/local_mc_plugin.php`.

## [4.0.2] - 2025-12-15

### Fixed
- **ESLint Complexity**: Refactored `connect.js` to reduce cyclomatic complexity below threshold.
- **Stylelint**: Removed all `!important` declarations from `styles.css`.
- **PHPUnit Compatibility**: Replaced `assertObjectHasProperty` with `assertTrue(property_exists(...))` for Moodle 4.0/PHPUnit 9 compatibility.

## [4.0.1] - 2025-12-15

### Fixed
- **Linting Issues**: Resolved ESLint warnings in AMD modules (camelCase variables).
- **PHPCS**: Fixed language string ordering in `lang/en/local_mc_plugin.php`.

## [4.0.0] - 2025-12-15

### Added
- **Output API Implementation**: Complete refactor of the admin interface to use Moodle's Output API (Renderers, Renderables, Templates).
- **Mustache Templates**: Modern, responsive UI components for:
  - Event Selector (with search, filtering, and bulk actions)
  - Connection Status (dynamic updates via AJAX)
  - Action Buttons (loading states and feedback)
- **AMD Modules**: Full migration of JavaScript logic to modular, dependency-managed AMD files.
- **Client-Side Rendering**: Dynamic UI updates using `core/templates` for better performance and user experience.

### Changed
- **Admin Settings**: Replaced legacy `admin_setting_configtext` with custom setting classes that delegate to the Output API.
- **JavaScript Architecture**: Removed all inline JavaScript; logic is now encapsulated in `amd/src/local/admin/*.js`.
- **Styling**: Cleaned up CSS and leveraged Bootstrap utility classes for consistent design.
- **Code Structure**: Improved separation of concerns between logic (PHP classes), presentation (Mustache), and behavior (JS).

### Removed
- Legacy inline HTML generation in admin settings.
- Unused CSS styles and redundant code.

## [3.2.0] - 2025-12-14

### Added
- Comprehensive PHPUnit test suite with full coverage
- GitHub Actions CI workflow with matrix testing
  - Moodle 4.0, 4.5 (LTS), and 5.0 support
  - PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 compatibility
  - PostgreSQL and MariaDB database testing
- Input validation and CSRF protection for all plugin endpoints
- Privacy API implementation for GDPR compliance
- PHPDoc comments for all functions and classes
- GPL license headers on all PHP files
- Plugin icon for Moodle marketplace

### Changed
- Improved code quality to meet Moodle Plugin Directory standards
- Enhanced error handling and logging
- Moved all hardcoded strings to language files
- Reorganized README with expanded documentation links
- **Achieved full Moodle coding standards compliance** - all 200+ phpcs violations fixed
  - Fixed variable naming conventions (snake_case to camelcase)
  - Fixed inline comment formatting (proper capitalization and punctuation)
  - Added documentation to empty catch blocks
  - Removed trailing whitespace from string literals
  - Fixed line length violations (132 and 180 character limits)
  - Removed unnecessary MOODLE_INTERNAL checks
  - Added missing class-level documentation
  - Replaced forbidden functions with Moodle-approved alternatives
  - Fixed interface ordering in privacy provider class
  - Fixed language string ordering (alphabetical)

### Fixed
- Moodle 4.0 compatibility issues with event validation
- Test file locations and namespace handling
- AMD module builds using Moodle's Rollup system
- All phpcs errors and warnings resolved (zero violations)
- Debug warning suppression and MOODLE_INTERNAL checks

### Security
- Added CSRF token validation for AJAX endpoints
- Input sanitization for all user-provided data

## [3.1.0] - 2025-12-03

### Added
- OAuth-style connection flow for simplified site linking
- One-click "Connect to MoodleConnect" button in plugin settings
- Automatic site key and secret exchange during connection
- Secure redirect handling between Moodle and MoodleConnect service
- Connection status indicator in admin interface
- Disconnect functionality to unlink sites

### Changed
- Simplified initial setup process (no manual site key entry required)
- Improved user experience for site connection workflow
- Enhanced security with automatic credential exchange

### Fixed
- Connection reliability improvements
- Better error messages during setup process

## [3.0.0] - 2025-11-XX

### Added
- Event observer system for capturing Moodle events
- Selective event monitoring via admin interface
- Event schema discovery and synchronization
- HMAC-based authentication for secure API communication
- Debug mode with detailed logging
- Schema sync page with AJAX-based UI
- Support for dynamic event field inspection
- Configurable MoodleConnect API endpoint

### Changed
- Initial stable release for production use

### Security
- Implemented HMAC signature verification for all API requests
- Secure storage of site credentials
- HTTPS-only communication with MoodleConnect service

## [2.0.0] - 2025-10-XX

### Added
- Beta release with core functionality
- Basic event capture and transmission
- Admin settings interface

## [1.0.0] - 2025-09-XX

### Added
- Initial alpha release
- Proof of concept implementation

---

## Version Numbering

This plugin follows Moodle's version numbering scheme:

- **version**: YYYYMMDDXX format (e.g., 2025120301)
- **release**: Semantic versioning (e.g., v3.1.0)

## Upgrade Notes

### Upgrading to 3.1.0

If you're upgrading from 3.0.0:
1. The plugin will automatically migrate your existing site key and secret
2. You'll see a new "Connect to MoodleConnect" interface
3. If already connected, your connection will remain active
4. No action required - existing triggers and configurations are preserved

### Upgrading to 3.0.0

First stable release - no upgrade path from earlier versions.

## Support

For issues, questions, or feature requests:
- GitHub Issues: https://github.com/kcanakdag/moodle-local_mc_plugin/issues
- Email: support@moodleconnect.com
- Documentation: https://moodleconnect.com/docs
