# EA Gaming Engine Translations

This directory contains translation files for the EA Gaming Engine plugin.

## Current Status

- **POT File**: `ea-gaming-engine.pot` (Template file with 219 translatable strings)
- **Text Domain**: `ea-gaming-engine`
- **Last Updated**: 2025-08-13

## Translation Process

### For Developers

To update the POT file after adding new translatable strings:

```bash
npm run i18n:make-pot
```

To generate JSON files for JavaScript translations:

```bash
npm run i18n:make-json
```

### For Translators

1. Copy `ea-gaming-engine.pot` to `ea-gaming-engine-{locale}.po` (e.g., `ea-gaming-engine-es_ES.po` for Spanish)
2. Translate all `msgstr` fields in the PO file
3. Generate MO file using tools like Poedit or gettext

### JavaScript Translations

The plugin uses `@wordpress/i18n` for JavaScript translations. After updating PO files, run:

```bash
npm run i18n:make-json
```

This generates JSON files that WordPress can use for JavaScript translations.

## Available Languages

Currently, only the English template (POT) file is available. Contributions for additional languages are welcome!

## Translatable String Categories

The plugin contains translations for:

- **Admin Interface**: Dashboard, settings, policies, analytics pages
- **Frontend Components**: Game launcher, arcade, leaderboard, stats
- **Game Messages**: Success/error messages, hints, instructions
- **Policy Messages**: Access restrictions, time limits, parent controls
- **Integration Messages**: LearnDash course/quiz interactions
- **Error Messages**: Validation errors, API responses

## Contributing Translations

1. Create a new PO file based on the POT template
2. Translate all strings while preserving placeholders (`%s`, `%d`, `%1$s`, etc.)
3. Pay attention to translator comments that explain placeholder meanings
4. Test translations in context
5. Submit via GitHub pull request or support channels

## Technical Notes

- All PHP strings use WordPress i18n functions (`__()`, `_e()`, `_n()`, etc.)
- JavaScript strings use `@wordpress/i18n` functions (`__()`, `_n()`, etc.)
- Translator comments are provided for strings with placeholders
- Text domain is consistently `ea-gaming-engine` throughout
- Plugin follows WordPress internationalization best practices