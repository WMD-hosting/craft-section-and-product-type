# Section And Product Type Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.0 - 2026-08-21
> ### Downgrade warning
> Once a field has been saved with the new View Mode or Template Value settings, rolling back to
> 2.0.x breaks the Control Panel, because the older code rejects the stored settings with an
> `UnknownPropertyException`. Remove them first with
> `php craft project-config/remove fields.<field-uid>.settings.viewMode` (and `.valueType`).

### Added
- New Entry Type field. Craft 5 decoupled entry types from sections, so this selects entry types directly. It has the same settings as the other three field types.
- New "View Mode" setting on every field type. "List" keeps the existing radio button and checkbox rendering, "Dropdown" renders a select menu instead, which is easier to work with when there are a lot of options. Existing fields default to "List", so nothing changes until the setting is switched.
- New "Template Value" setting on every field type. "IDs" is the default and keeps returning the selected IDs exactly as before. "Objects" returns an object whose `all()`, `ids()`, `handles()` and `names()` methods give you the sections, product types, tag groups or entry types themselves. The object can still be handed straight to an element query, and the stored value is identical either way.
- GraphQL support on every field type, for both queries and mutations. These fields were previously invisible to GraphQL.
- Element index columns now show the selected names. Previously the column was blank for multi select fields and showed a bare numeric ID for single select fields.
- "Select By Text In Handle" is now available on the Product Type and Tag Group fields, matching the Section field.
- The field settings now have a dedicated filter box that narrows the allowed and excluded lists by name or by handle.

### Changed
- The `craftcms/cms` requirement is now `^5.0.0` instead of `^5.0.0-alpha.1`.

### Fixed
- The documentation and issues links in `composer.json` pointed at a malformed URL, so the plugin store listing linked nowhere.
- The field settings list filter never matched anything on Craft 5, because it looked for a CSS class that Craft no longer renders inside a checkbox group. It is now a scoped asset bundle shared by all four field types, and it filters the lists instead of colouring the matches.

## 2.0.8 - 2024-03-26
### Changed
- Maintenance release.

## 2.0.7 - 2024-03-26
### Changed
- Maintenance release.

## 2.0.6 - 2024-03-19
### Changed
- Maintenance release.

## 2.0.5 - 2024-03-19
### Changed
- Maintenance release.

## 2.0.4 - 2024-03-13
### Added
- Craft CMS 5 support.

## 2.0.3 - 2023-05-12
### Added
- Select section by part of handle functional was added

## 2.0.2 - 2023-05-12
### Changed
- Maintenance release.

## 2.0.1 - 2022-10-25
### Changed
- Craft CMS 4 is now the minimum requirement. Craft CMS 3 is no longer supported.

## 2.0.0 - 2022-05-20
### Changed
- Maintenance release.

## 1.1.0 - 2022-05-20
### Added
- Craft CMS 4 support, alongside Craft CMS 3.

## 1.0.5 - 2021-02-24
### Changed
- Maintenance release.

## 1.0.4 - 2021-01-29
### Changed
- Maintenance release.

## 1.0.3 - 2021-01-15
### Changed
- Maintenance release.

## 1.0.2 - 2021-01-15
### Changed
- Update logic

## 1.0.1 - 2021-01-15
### Changed
- Maintenance release.

## 1.0.0 - 2020-12-23
### Added
- Initial release
