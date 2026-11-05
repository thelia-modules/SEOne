# Changelog

## 1.6.1

- The store card of the configuration screen (default title, description and keywords) has its own language selector, shows the values of the chosen edition language and saves into it. It used to show the values of the edition language kept in the session and to save into the language of the back-office itself, so a value typed for another language overwrote the one of the back-office language.

## 1.6.0

- The `layout.head.top` theme hook takes `og_title` and `og_description`: a page that asks for other words when it is shared gets them in `og:title`, `og:description`, `twitter:title` and `twitter:description`. Without them, these tags keep the title and the description of the page.

## 1.5.2

- The `main.head-bottom` hook of the themes written for Thelia 2 is subscribed again, next to the `layout.head.bottom` theme hook. It prints the same robots meta tag and JSON-LD, and a theme that calls both gets them once.
- The `meta_hook.html` template is back at its path; it calls the `main.head-bottom` hook.
