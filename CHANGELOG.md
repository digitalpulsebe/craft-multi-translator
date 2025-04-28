# Release Notes for Multi Translator

## Unreleased

### Added

- Extra permission for bulk translating

### Fixed

- use multiTranslateContent permission for review template

## 2.11.0 - 2025-04-08

### Added

- translate nested Entries inside CKEditor (fixes #35)

## 2.10.7 - 2025-03-28

### Fixed

- use className(element) instead of deprecated className (fixes #44)

## 2.10.6 - 2025-03-26

### Fixed

- fix error getDisabledFields(): Return value must be of type array, string returned x2

## 2.10.5 - 2025-03-25

### Added

- Disabled fields setting

### Fixed

- fix error getDisabledFields(): Return value must be of type array, string returned

## 2.10.4 - 2025-02-06

### Fixed

- fix link-field losing type after serialisation (#38)
- fix UI padding lost after Craft update (#36)

### Changed

- Changed prompt for chat-gpt to avoid additional text in short prompts (#37) Thanks @pascal-blaser

## 2.10.3 - 2025-01-06

### Changed

- new icon-mask logo

## 2.10.2 - 2024-12-13

### Fixed

- update existing glossaries name and language pair

## 2.10.1 - 2024-12-03

### Changed

- update legend title in review template

## 2.10.0 - 2024-12-03

### Added

- Add Linkit field support (Merged PR #28)

## 2.9.0 - 2024-11-29

### Added

- on the fly settings override with "more options" button in the sidebar
- translate to another target site from the sidebar

## 2.8.0 - 2024-11-15

### Added

- setting: save as draft, to always create a new draft

### Changed

- moved settings to database

## 2.7.3 - 2024-11-14

### Fixed

- check against PropagationMethod enum instead of constant

## 2.7.2 - 2024-10-30

### Fixed

- fix overwriting neo blocks for all propagation methods

## 2.7.1 - 2024-10-21

### Fixed

- read neo propagationMethod instead of translationMethod (fixes #23)

## 2.7.0 - 2024-08-13

### Added

- added support for verbb\hyper\fields\HyperField
- more openai models in settings list
- translate alt fields for assets

### Fixed

- fix js syntax when registering action trigger
- only translate title when translatable
- use temperature setting in openai body
- fix special case to avoid neo overwriting blocks in all languages, reported by @lenvanessen in issue #16

## 2.6.0 - 2024-07-09

### Added
- added support for Asset Elements

## 2.5.3 - 2024-07-08

### Added
- added support for abmat\tinymce\Field; PR from @HigumaSan4050

## 2.5.2 - 2024-06-18

### Fixed
- glossaries table in install migration as well

## 2.5.1 - 2024-06-12

### Fixed
- supported language list glossaries

## 2.5.0 - 2024-06-12

### Added
- DeepL Glossaries

## 2.4.0 - 2024-04-19

### Added
- Update internal CKeditor links
- Support for Vizy field

## 2.3.0 - 2024-04-05

### Added
- Add support for SEO field from ether/seo
- Add support for SEO field from nystudio107/craft-seomatic

## 2.2.0 - 2024-03-27

### Added
- Commerce products support

### Fixed
- avoid html entities in google translated results

## 2.1.0 - 2024-03-15

### Added
- OpenAI API (ChatGPT) support

## 2.0.1 - 2024-02-27

### Added
- debug logging
- support for drafts
- log validation errors

### Fixed
- support voor regional pt target locales for Deepl API
- ignore revisions
- typo double $
- Check that logging dispatcher exists, to avoid error when testing
- fix Undefined variable $locale when processing empty sourceLocale

## 2.0.0 - 2024-02-09

### Updated
- Craft 5 support

## 1.16.0 - 2025-04-28

### Added

- Extra permission for bulk translating

### Fixed

- use multiTranslateContent permission for review template

## 1.15.8 - 2025-03-26

### Fixed

- fix error getDisabledFields(): Return value must be of type array, string returned x2

## 1.15.7 - 2025-03-25

### Fixed

- fix saving disabledFields in settings

## 1.15.6 - 2025-03-25

### Added

- Disabled fields setting

### Fixed

- fix error getDisabledFields(): Return value must be of type array, string returned

## 1.15.5 - 2025-02-06 

### Fixed

- fix link-field losing type after serialisation (#38)

### Changed
 
- Changed prompt for chat-gpt to avoid additional text in short prompts (#37) Thanks @pascal-blaser

## 1.15.4 - 2025-01-20

### Fixed

- fix matrix blocks translated content is not saved when translating to drafts (fixes issue #34)

## 1.15.3 - 2025-01-06

### Changed

- new icon-mask logo

## 1.15.2 - 2024-12-13

### Fixed

- update existing glossaries name and language pair

## 1.15.1 - 2024-12-03

### Changed

- update legend title in review template

## 1.15.0 - 2024-12-03

### Added

- Add Linkit field support (Merged PR #28)

## 1.14.0 - 2024-11-27

### Added

- on the fly settings override with "more options" button in the sidebar
- translate to another target site from the sidebar

## 1.13.0 - 2024-11-15

### Added

- setting: save as draft, to always create a new draft

### Changed

- moved settings to database

## 1.12.2 - 2024-10-30

### Fixed

- fix overwriting neo blocks for all propagation methods

## 1.12.1 - 2024-10-23

### Fixed

- read neo propagationMethod instead of translationMethod (fixes #23)

## 1.12.0 - 2024-08-13

### Added

- added support for verbb\hyper\fields\HyperField
- more openai models in settings list

### Fixed

- fix js syntax when registering action trigger
- only translate title when translatable
- use temperature setting in openai body

## 1.11.0 - 2024-07-09

### Added
- added support for Asset Elements
- added support for abmat\tinymce\Field; PR from @HigumaSan4050

## 1.10.2 - 2024-06-18

### Fixed
- glossaries table in install migration as well

## 1.10.1 - 2024-06-12

### Fixed
- supported language list glossaries

## 1.10.0 - 2024-06-12

### Added
- DeepL Glossaries

## 1.9.0 - 2024-04-19

### Added
- Update internal CKeditor links
- Support for Vizy field

## 1.8.1 - 2024-04-05

### Fixed
- typo classname

## 1.8.0 - 2024-04-05

### Added
- Add support for SEO field from ether/seo
- Add support for SEO field from nystudio107/craft-seomatic

## 1.7.1 - 2024-03-27

### Fixed
- avoid html entities in google translated results

## 1.7.0 - 2024-03-27

### Added
- Commerce products support

## 1.6.0 - 2024-03-15

### Added
- OpenAI API (ChatGPT) support

## 1.5.6 - 2024-02-27

### Fixed
- fix Undefined variable $locale when processing empty sourceLocale

## 1.5.5 - 2024-02-26

### Fixed
- Check that logging dispatcher exists, to avoid error when testing

## 1.5.4 - 2024-02-26

### Fixed
- typo double $

## 1.5.3 - 2024-02-26

### Added
- log validation errors

## 1.5.2 - 2024-02-26

### Added
- debug logging

### Fixed
- support voor regional pt target locales for Deepl API

## 1.5.0 - 2024-02-23

### Added
- support for drafts

### Fixed
- ignore revisions

## 1.4.2 - 2024-01-02

### Updated
- accept empty sourceLocales

## 1.4.1 - 2023-12-28

### Fixed
- only process text fields in table fields

## 1.4.0 - 2023-12-27

### Added
- Google Cloud Translation integration

## 1.3.2 - 2023-12-27

### Fixed
- unknown mode

## 1.3.1 - 2023-12-20

### Added
- logo

## 1.3.0 - 2023-12-18

### Updated
- renamed settings

### Removed
- copy only action

## 1.2.0 - 2023-12-15

### Updated
- renamed plugin to Multi Translator

## 1.1.1 - 2023-11-15

### Fixed
- fix keep enabled status of elements

## 1.1.0 - 2023-11-15

### Added
- copy only action

## 1.0.2 - 2023-10-18

### Fixed
- find disabled elements

## 1.0.1 - 2023-10-18

### Fixed
- only allow copy from existing sites
- create entries for some propagation methods

## 1.0.0
- Initial release
