# Changelog

## 1.7.1

- A category page no longer answers HTTP 500 when `page` or `limit` has an unexpected shape in the query string (`?page[]=1`, `?limit[]=1`, `?limit=abc`): the structured data falls back on the first page and on the limit set in the module configuration. A `limit` above 100 is ignored the same way. The page parameters read by the SEO tools of the module (`_view` and the object id) ignore an array value too.

## 1.7.0

- The JSON-LD of a product page and of the products it is related or similar to tells the price the visitor is shown. Where the shop or the product shows its prices to identified customers only, or on request, a visitor gets the `Product` node with no `offers`, so no `price`. Where the price is shown, it is the price the core resolves for the visitor (price list, group price, discounts) instead of the catalogue price. A shop that shows every price, and a core that has no price display mode, print what they always did.
- The `%taxed_price%` and `%untaxed_price%` variables of the meta templates follow the same rule: empty for a visitor who is not shown the price, the price the customer pays otherwise.

## 1.6.2

- A structured data block with no data (a listener of `BETTER_SEO_MICRO_DATA` that empties the microdata) prints no `<script type="application/ld+json">` tag any more. It used to print `[]`.

## 1.6.1

- The store card of the configuration screen (default title, description and keywords) has its own language selector, shows the values of the chosen edition language and saves into it. It used to show the values of the edition language kept in the session and to save into the language of the back-office itself, so a value typed for another language overwrote the one of the back-office language.

## 1.6.0

- The `layout.head.top` theme hook takes `og_title` and `og_description`: a page that asks for other words when it is shared gets them in `og:title`, `og:description`, `twitter:title` and `twitter:description`. Without them, these tags keep the title and the description of the page.

## 1.5.2

- The `main.head-bottom` hook of the themes written for Thelia 2 is subscribed again, next to the `layout.head.bottom` theme hook. It prints the same robots meta tag and JSON-LD, and a theme that calls both gets them once.
- The `meta_hook.html` template is back at its path; it calls the `main.head-bottom` hook.
