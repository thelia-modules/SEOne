# Changelog

## 1.6.0

- The `layout.head.top` theme hook takes `og_title` and `og_description`: a page that asks for other words when it is shared gets them in `og:title`, `og:description`, `twitter:title` and `twitter:description`. Without them, these tags keep the title and the description of the page.

## 1.5.2

- The `main.head-bottom` hook of the themes written for Thelia 2 is subscribed again, next to the `layout.head.bottom` theme hook. It prints the same robots meta tag and JSON-LD, and a theme that calls both gets them once.
- The `meta_hook.html` template is back at its path; it calls the `main.head-bottom` hook.
