=== Headless Block Styles ===
Contributors: fusan
Tags: headless, rest-api, gutenberg, blocks, decoupled
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exposes Gutenberg block inline styles and the global stylesheet over the REST API for headless frontends like Next.js.

== Description ==

Headless Block Styles brings full Gutenberg block styling to any headless frontend (e.g. Next.js) using the WordPress REST API — no WPGraphQL required.

It does two things:

1. **Serves the global stylesheet** (`wp_get_global_stylesheet()`): all `--wp--preset--*` CSS variables, preset classes (`has-*-color`, `has-*-font-size`, …), and theme/user styles — the CSS WordPress would normally enqueue on its own frontend.
2. **Computes per-block inline styles on the server** with the WordPress Style Engine and adds a `block_data` field to post REST responses, so your frontend receives ready-to-spread React style objects instead of deriving them from raw block attributes in JavaScript.

= REST API =

**`GET /wp-json/headless-block-styles/v1/stylesheet`**

* `types` — comma-separated subset of `variables,presets,styles,base-layout-styles`. Omit for all.
* `format` — `json` (default) or `css` (raw CSS, usable directly in a `<link>` tag).

**`GET /wp-json/headless-block-styles/v1/theme`**

Returns merged `theme.json` settings (`wp_get_global_settings()`) — color palette, font sizes, layout sizes.

**`block_data` field on posts and pages**

Every REST-enabled post type response gains a `block_data` field: the parsed block tree where each block carries `inlineStyles` (camelCase, ready for a React `style` prop), `classNames` (editor-parity `has-*` classes), `attributes`, `innerHTML`, and recursive `innerBlocks`.

`inlineStyles` covers the `style` attribute (spacing, borders, typography, colors — via the WP Style Engine), preset attributes (`backgroundColor`, `textColor`, `gradient`, `borderColor`, `fontSize`, `fontFamily`), and layout (`width`, `height`, `layout.contentSize/wideSize/justifyContent`).

Not needed on a request? Exclude it: `/wp/v2/posts?_fields=id,title,content`.

= Filters =

* `headless_block_styles_block_inline_styles` — modify a block's computed style object. Args: `$styles`, `$attrs`.
* `headless_block_styles_block_class_names` — modify a block's computed class names. Args: `$classnames`, `$attrs`.
* `headless_block_styles_post_types` — which post types get the `block_data` field.

Full documentation and Next.js usage examples: [github.com/FuSan21/headless-block-styles](https://github.com/FuSan21/headless-block-styles)

== Installation ==

1. Upload the `headless-block-styles` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin through the Plugins menu.
3. Fetch `/wp-json/headless-block-styles/v1/stylesheet` from your frontend and inject the CSS, and read `block_data` from your post/page REST responses.

== Frequently Asked Questions ==

= Does this require WPGraphQL or any other plugin? =

No. It only uses the built-in WordPress REST API and Style Engine.

= Are the endpoints public? =

The stylesheet and theme endpoints are public, matching what WordPress already outputs publicly on its own frontend. The `block_data` field follows the visibility of the post it belongs to — private and password-protected content is not exposed.

= Does the stylesheet include the core block library CSS? =

No, same as the WordPress global stylesheet itself. For full parity, import `@wordpress/block-library` styles in your frontend or copy `wp-includes/css/dist/block-library/style.css`.

== Changelog ==

= 0.1.0 =
* Initial release: global stylesheet and theme settings REST endpoints, `block_data` REST field with server-computed inline styles and class names.
