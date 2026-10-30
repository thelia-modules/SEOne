# Changelog

## 1.5.2

- The `main.head-bottom` hook of the themes written for Thelia 2 is subscribed again, next to the `layout.head.bottom` theme hook. It prints the same robots meta tag and JSON-LD, and a theme that calls both gets them once.
- The `meta_hook.html` template is back at its path; it calls the `main.head-bottom` hook.
