# Section + Entry type + Product Type

[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-6.x-E5422B)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-777BB4)](https://www.php.net/)
[![Packagist](https://img.shields.io/packagist/v/wmd/section-and-product-type)](https://packagist.org/packages/wmd/section-and-product-type)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

Craft CMS field types for selecting parts of your **content model** rather than content itself.

Instead of relating to specific entries, these fields let an author pick a *section*, an
*entry type* or a Commerce *product type*. You then use that choice to drive a
query. That makes them a good fit for page builders and "list the latest N from X" blocks,
where the editor decides *what kind* of content a block pulls in.

## Field types

| Field type | Selects | Requires |
| --- | --- | --- |
| **Section** | Entry sections | |
| **Entry Type** | Entry types | |
| **Product Type** | Commerce product types | Craft Commerce |

## Requirements

- Craft CMS 6.0.0-beta.1 or later
- PHP 8.5 or later
- Craft Commerce, for the Product Type field only

## Installation

From the Craft Control Panel, go to **Plugin Store**, search for *Section + Entry type + Product Type* and
click **Install**.

Or from your terminal:

```bash
composer require wmd/section-and-product-type
php artisan craft:plugin:install section-and-product-type
```

## Field settings

Every field type shares the same settings.

| Setting | What it does |
| --- | --- |
| **Multiple Select** | Lets the author pick more than one option. |
| **View Mode** | How the options are presented. `List` renders radio buttons, or checkboxes when Multiple Select is on. `Dropdown` renders a select menu, or a searchable multi-select when Multiple Select is on. Defaults to `List`. |
| **Template Value** | What the field returns in templates: `IDs` (default) or `Objects`. See [Template value](#template-value-ids-or-objects). |
| **Select All** | Makes everything available for selection, minus anything in the exclude list. |
| **Select By Text In Handle** | Also allows anything whose handle contains this text, for example `category`. |
| **Allowed / Excluded** | The explicit allow and exclude lists. Excluded always wins: an excluded option is never offered, whether Select All, the Allowed list or the handle text would offer it. Both are searchable multi-selects: type part of a name or handle to narrow a long list. |

The allow and exclude lists are saved in project config as UIDs, so a field means the same
sections (or entry types, product types) on every environment. Fields saved before
2.3.0 hold numeric IDs; those keep working on the install that saved them and are written as
UIDs the next time the field is saved. Field *values* on entries are content, not project
config, and stay IDs: an integer, or a list of integers with Multiple Select.

A Product Type field keeps its lists while Commerce is not installed or is disabled. No
product type exists then, so the settings show the lists as stored references (UIDs) with a
notice instead of names, and saving the field leaves them as they are. Entries keep the
product types they hold too.

Choose `Dropdown` when a site has enough sections or entry types that a wall of
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
| `all()` | The selected models (`Section`, `EntryType` or `ProductType`) |
| `one()` | The first selected model, or `null` |
| `ids()` | The selected IDs |
| `handles()` | The selected handles |
| `names()` | The selected names |
| `isEmpty()` | Whether nothing is selected |
| `missingIds()` | The selected IDs that no longer name anything, see [Deleted items](#deleted-items) |

The value stored in the database is identical in both modes, so switching this setting never
touches your content.

## Deleted items

When a section (entry type, product type) is deleted, entries that selected it still hold its ID.
The field treats that the same way with both Template Value settings:

- The input lists the ID as **Missing section (ID 12)**, still selected, so an author can see it
  and take it out.
- Until someone does, the entry does not validate ("*{field} is invalid.*") and the ID stays in
  the stored value. Nothing is dropped behind the author's back.
- In templates, `Objects` hands you only what exists (`all()`, `ids()`, `handles()`, `names()`
  and iterating); `missingIds()` lists the rest. `IDs` returns the stored IDs as they are.

While Commerce is not installed or is disabled, no product type can be checked, so a Product Type
field rejects nothing and its values are kept until Commerce is back.

## GraphQL

All three field types are available in GraphQL, for both queries and mutations. They return the
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

## MCP and agents

Each field describes the values it accepts through Craft 6's `getInputSchema()`: the IDs an
author may pick, each with its name and handle. MCP clients that create or update entries see
the valid IDs and send one of them; an ID that names no section (entry type, product type) is
rejected when the entry is validated (see [Deleted items](#deleted-items)).

## Version support

| Plugin | Craft CMS |
| --- | --- |
| 3.x | 6.x |
| 2.2.x - 2.3.x | 5.x |
| 2.1.x | 5.x |
| 2.0.4 - 2.0.8 | 5.x |
| 2.0.1 - 2.0.3 | 4.x |
| 2.0.0 | 3.x, 4.x |
| 1.1.0 | 3.x, 4.x |
| 1.0.x | 3.x |

### Craft 6

3.x is the Craft 6 version. The Section, Entry Type and Product Type fields keep their class
names, settings and stored values, so they carry over when a site is upgraded from Craft 5.
Values saved by 2.x as JSON strings (`"5"`, `"[1,2]"`) are read as before and written back as
plain integers on the next save. Product Type needs Commerce 6.

### Tag Group fields

Craft 6 has no tags, so 3.x has no Tag Group field type. A Tag Group field that a site brings
along from Craft 5 is not deleted. Craft treats it like any field whose type is not installed:

- In **Settings → Fields** it is listed as *Missing*, and its settings cannot be saved
  (`Unable to find component class 'wmd\sectionandproducttype\fields\TagGroupField'`). Its
  settings stay in project config as they were.
- In an entry it shows as a disabled placeholder. Entries keep the tag group IDs they stored,
  also when they are saved again.

Those IDs have nothing to point at in Craft 6. Take the field out of your templates, then delete
it in **Settings → Fields** once you no longer need the stored values.

## Credits

The plugin is based on the logic of
[charliedevelopment/craft3-section-field](https://github.com/charliedevelopment/craft3-section-field),
many thanks to the author.

Brought to you by [WMD Hosting](https://wmd.hosting/)

## License

MIT. See [LICENSE.md](LICENSE.md).
