# SEOne

SEO tools for a Thelia 3 shop, in one module. It writes the title, description,
canonical URL, hreflang links, robots meta tag, breadcrumb and JSON-LD structured
data of the front office, serves `robots.txt`, and adds an SEO block to the edit
page of each record in the back office.

## Compatibility

- Thelia 3.0.0 or later (`Config/module.xml`)
- PHP 8.3+ (the module uses typed class constants)
- A front theme that calls the `layout.head.top` and `layout.head.bottom` theme
  hooks, as Flexy does. On the `main.head-bottom` hook of a classic theme the
  module prints the robots meta tag, the JSON-LD and the canonical link.

## Installation

```bash
composer require thelia/seone-module
php Thelia module:activate SEOne
php Thelia cache:clear
```

Activation creates the `seone` and `robots` tables and writes a default
`robots.txt` for each domain that has none.

## What it does

### On every page

- **Title and description.** The record's own meta fields come first. When they
  are empty, the [meta template](#meta-templates) of that kind of page applies,
  then the record title, then the store title and description set on the module
  configuration page.
- **Canonical URL.** The current URL without the script name, with `?page=N` kept
  on category and folder pages beyond the first. A canonical typed on a record
  replaces it.
- **hreflang.** One `alternate` link per active, visible language, plus
  `x-default` for the default language. A product, category, content, folder or
  brand page links to the rewritten URL of each language, once the shop agreed
  to serve that page.
- **Robots meta tag.** `noindex` and `nofollow` ticked on a record become
  `<meta name="robots">` on its page.
- **JSON-LD.** A `LocalBusiness` node for the store, and a node for the page:
  `Product` for a product, `ItemList` for a category, `Article` for a content,
  `Guide` for a folder and for a Page module page. A record can also carry its
  own JSON-LD, which is printed after the generated ones.
- **Breadcrumb.** The category or folder path of the record, available to
  themes and printed as a `BreadcrumbList`.

### On each record

The SEO tab of the edit page (hook `tab-seo.bottom`) gets these fields, saved per
language:

- canonical URL override
- noindex and nofollow
- H1
- JSON-LD
- five "Mesh" text fields (`mesh_1` to `mesh_5`) and five "Mesh links", each
  with a link text and a URL (`mesh_text_N`, `mesh_url_N`)

The module does not print the mesh fields itself. A theme reads them, with the
H1, through the `seone_loop` loop (`object_id`, `object_type`, `lang_id`).

### Meta templates

On the module configuration page, one title template and one description template
per kind of page (product, category, content, folder). A template is plain text
with `%variable%` markers, with no condition or function call. A template that
names an unknown variable is refused.

| Page | Variables |
|---|---|
| Product | `title`, `description`, `chapo`, `ref`, `brand`, `category_title`, `taxed_price`, `untaxed_price` |
| Category | `title`, `description`, `chapo`, `number_products`, `category_parent_title` |
| Content | `title`, `description`, `chapo`, `folder_parent_title` |
| Folder | `title`, `description`, `chapo`, `folder_parent_title` |

`%store_name%` works in every template. An empty variable leaves no stray
separator behind. The result is cut on a word boundary at 60 characters for a
title and 160 for a description; both limits can be changed (10 to 500). A
preview on the same screen renders a template against a real record.

### robots.txt

`/robots.txt` serves the content stored for the current domain, edited per domain
on the configuration page. A domain with no entry gets a 404. The default
content disallows `/cart` and `/404` and points to `<domain>/sitemap`.

## Configuration

On the module configuration page of the back office:

- **Store**: title, description and keywords, per language. They are the fallback
  of every page that has nothing more specific.
- **Category limit**: the number of products listed in the structured data of a
  category page. Without it, every product of the category is listed.
- **Meta templates** and **robots.txt**, described above.

## In a theme

Flexy needs nothing: the two theme hooks print the title, description,
canonical, Open Graph and Twitter tags, hreflang, breadcrumb and JSON-LD.

A theme of its own can call these Twig functions:

| Function | Returns |
|---|---|
| `SEOneMicroData()` | robots meta tag and JSON-LD of the current page |
| `SEOnePageTitle()`, `SEOnePageDesc()`, `SEOnePageH1()` | the title, description and H1 of the current page |
| `SEOnePageCanonical()` | the canonical URL |
| `SEOneHreflang()` | the alternate links |
| `SEOneBreadcrumb()` | the breadcrumb items |
| `SEOneBreadcrumbJsonLd(items)` | the `BreadcrumbList` script |
| `SEOneWebSite()`, `SEOneWebPage()`, `SEOneContactPage()`, `SEOneLocalBusiness(logo)`, `SEOneBreadcrumbList(items)` | one JSON-LD script per schema.org type |

## Extending

- A module that serves its own pages implements
  `SEOne\Service\SeoDefaultModels\SeoElementInterface` for its title,
  description, H1, microdata and breadcrumb. The service is picked up by its
  `seone.type` tag.
- The same module can implement
  `SEOne\Service\MetaTemplate\VariableResolverInterface` to get a pair of
  template fields on the configuration page.
- Events let another module change the output: `seone.url.generate.canonical`,
  `get.alternate.hreflang`, `better.seo.page.title`, `better.seo.page.desc`,
  `better.seo.page.h1`, `better.seo.page.micro.data`, `better.seo.micro.data`
  and `seone.page.breadcrumb`.
