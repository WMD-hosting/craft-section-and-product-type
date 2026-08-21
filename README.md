# Section + Product Type

[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.x-E5422B)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)](https://www.php.net/)
[![Packagist](https://img.shields.io/packagist/v/wmd/section-and-product-type)](https://packagist.org/packages/wmd/section-and-product-type)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

Craft CMS field types for selecting parts of your **content model** rather than content itself.

Instead of relating to specific entries, these fields let an author pick a *section*, an
*entry type*, a Commerce *product type* or a *tag group*. You then use that choice to drive a
query. That makes them a good fit for page builders and "list the latest N from X" blocks,
where the editor decides *what kind* of content a block pulls in.

> **Craft 5.** This plugin supports Craft CMS 5. The repository is named `craft3-...` for
> historical reasons only, dating back to its first release in 2020. See
> [Version support](#version-support) for the full history.

## Field types

| Field type | Selects | Requires |
| --- | --- | --- |
| **Section** | Entry sections | |
| **Entry Type** | Entry types (Craft 5 decoupled these from sections) | |
| **Product Type** | Commerce product types | Craft Commerce |
| **Tag Group** | Tag groups | |

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later
- Craft Commerce, for the Product Type field only

## Installation

From the Craft Control Panel, go to **Plugin Store**, search for *Section + Product Type* and
click **Install**.

Or from your terminal:

```bash
composer require wmd/section-and-product-type
php craft plugin/install section-and-product-type
```

## Field settings

Every field type shares the same settings.

| Setting | What it does |
| --- | --- |
| **Multiple Select** | Lets the author pick more than one option. |
| **View Mode** | How the options are presented: `List` renders radio buttons, or checkboxes when Multiple Select is on. `Dropdown` renders a select menu, or a multi-select. Defaults to `List`. |
| **Template Value** | What the field returns in templates: `IDs` (default) or `Objects`. See [Template value](#template-value-ids-or-objects). |
| **Select All** | Makes everything available for selection, minus anything in the exclude list. |
| **Select By Text In Handle** | Also allows anything whose handle contains this text, for example `category`. |
| **Allowed / Exclude** | The explicit allow and exclude lists. A filter box above them narrows long lists by name or handle. |

Choose `Dropdown` when a site has enough sections, entry types or tag groups that a wall of
radio buttons becomes hard to scan. The stored value is identical in both view modes, so you
can switch back and forth without touching your content.

## Template usage

By default a field returns the selected ID, or an array of IDs when Multiple Select is on.
Hand that straight to an element query:

```twig
{# Section field #}
{% set entries = craft.entries().sectionId(entry.mySectionField).all() %}

{# Entry Type field #}
{% set entries = craft.entries().typeId(entry.myEntryTypeField).all() %}

{# Product Type field #}
{% set products = craft.products().typeId(entry.myProductTypeField).all() %}

{# Tag Group field #}
{% set tags = craft.tags().groupId(entry.myTagGroupField).all() %}
```

### Template value: IDs or Objects

`IDs` is the default and is what the plugin has always returned, so existing templates keep
working untouched.

Set **Template Value** to `Objects` and the field returns an object that still behaves like the
list of IDs, so it can go straight into an element query as above, but that also hands you the
underlying models:

```twig
{% set field = entry.mySectionField %}

{{ field.ids()|join(', ') }}       {# 2, 8 #}
{{ field.handles()|join(', ') }}   {# articles, globalElements #}
{{ field.names()|join(', ') }}     {# Articles, Global Elements #}

{% for section in field.all() %}
    {{ section.name }} ({{ section.handle }})
{% endfor %}

{# still works exactly as it does with IDs #}
{% set entries = craft.entries().sectionId(field).all() %}
```

| Method | Returns |
| --- | --- |
| `all()` | The selected models (`Section`, `EntryType`, `ProductType` or `TagGroup`) |
| `one()` | The first selected model, or `null` |
| `ids()` | The selected IDs |
| `handles()` | The selected handles |
| `names()` | The selected names |
| `isEmpty()` | Whether nothing is selected |

The value stored in the database is identical in both modes, so switching this setting never
touches your content.

## GraphQL

All four field types are available in GraphQL, for both queries and mutations. They return the
selected IDs: `Int`, or `[Int]` when Multiple Select is on.

```graphql
{
  entries(section: "pages") {
    ... on page_Entry {
      mySectionField
    }
  }
}
```

## Version support

| Plugin | Craft CMS |
| --- | --- |
| 2.1.x | 5.x |
| 2.0.4 - 2.0.8 | 5.x |
| 2.0.1 - 2.0.3 | 4.x |
| 2.0.0 | 3.x, 4.x |
| 1.1.0 | 3.x, 4.x |
| 1.0.x | 3.x |

### Upgrading and downgrading

Upgrading is safe: new settings default to the plugin's previous behaviour, so existing fields
render and store exactly as before until you change a setting.

**Downgrading from 2.1.0 to 2.0.x is not safe** once you have saved a field with the new
`View Mode` or `Template Value` settings. The older code rejects those stored settings with an
`UnknownPropertyException`, which breaks the Control Panel, not just the affected fields. If you
need to roll back, remove the settings first:

```bash
php craft project-config/remove fields.<field-uid>.settings.viewMode
php craft project-config/remove fields.<field-uid>.settings.valueType
```

## Credits

The plugin is based on the logic of
[charliedevelopment/craft3-section-field](https://github.com/charliedevelopment/craft3-section-field),
many thanks to the author.

Brought to you by [WMD Hosting](https://wmd.hosting/)

## License

MIT. See [LICENSE.md](LICENSE.md).
