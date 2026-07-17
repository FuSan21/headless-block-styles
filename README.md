# Headless Block Styles

A standalone WordPress plugin that brings full Gutenberg block styling to **any** headless frontend (e.g. Next.js) using the **REST API** — no WPGraphQL required.

It does two things:

1. **Serves the global stylesheet** (`wp_get_global_stylesheet()`): all `--wp--preset--*` CSS variables, preset classes (`has-*-color`, `has-*-font-size`, …), and theme/user styles — the CSS WordPress would normally enqueue on its own frontend.
2. **Computes per-block inline styles on the server** with the WordPress Style Engine and adds a `block_data` field to post REST responses, so your Next.js app receives ready-to-spread React style objects instead of deriving them from raw block attributes in JS.

## Installation

Copy the `headless-block-styles` folder into `wp-content/plugins/` and activate it. Requires WordPress 6.1+.

## REST API

### `GET /wp-json/headless-block-styles/v1/stylesheet`

| Param | Description |
| --- | --- |
| `types` | Comma-separated subset of `variables,presets,styles,base-layout-styles`. Omit for all. |
| `format` | `json` (default, `{ "css": "..." }`) or `css` (raw CSS — usable directly in a `<link>` tag). |

### `GET /wp-json/headless-block-styles/v1/theme`

Returns merged `theme.json` settings (`wp_get_global_settings()`) — color palette, font sizes, layout sizes. Useful if you want raw values instead of CSS variables.

### `block_data` field on posts/pages

Every REST-enabled post type response (`/wp/v2/posts`, `/wp/v2/pages`, …) gains a `block_data` field: the parsed block tree where each block has:

```jsonc
{
  "name": "core/paragraph",
  "attributes": { "backgroundColor": "cyan-bluish-gray", "textColor": "secondary" },
  "inlineStyles": {
    // camelCase, ready for a React style prop
    "backgroundColor": "var(--wp--preset--color--cyan-bluish-gray)",
    "color": "var(--wp--preset--color--secondary)"
  },
  "classNames": ["has-background", "has-cyan-bluish-gray-background-color", "has-text-color", "has-secondary-color"],
  "innerHTML": "<p>...</p>",
  "innerBlocks": []
}
```

`inlineStyles` covers: the `style` attribute (spacing, borders, typography, colors — via the WP Style Engine), preset attributes (`backgroundColor`, `textColor`, `gradient`, `borderColor`, `fontSize`, `fontFamily`), and layout (`width`, `height`, `layout.contentSize/wideSize/justifyContent`).

Not needed on a request? Exclude it: `/wp/v2/posts?_fields=id,title,content`.

## Next.js usage

### 1. Load the global stylesheet (App Router)

```jsx
// app/layout.jsx
async function getGlobalStylesheet() {
  const res = await fetch(
    `${process.env.NEXT_PUBLIC_WP_URL}/wp-json/headless-block-styles/v1/stylesheet`,
    { next: { revalidate: 3600 } },
  );
  const { css } = await res.json();
  return css;
}

export default async function RootLayout({ children }) {
  const css = await getGlobalStylesheet();
  return (
    <html lang="en">
      <head>
        <style id="wp-global-styles" dangerouslySetInnerHTML={{ __html: css }} />
      </head>
      <body>{children}</body>
    </html>
  );
}
```

Or link it directly (no JS parsing needed): `<link rel="stylesheet" href="https://wp.example.com/wp-json/headless-block-styles/v1/stylesheet?format=css" />`

Or download it at build time:

```json
// package.json
"scripts": {
  "prebuild": "curl -s \"$WP_URL/wp-json/headless-block-styles/v1/stylesheet?format=css\" -o styles/globalStylesheet.css"
}
```

### 2. Render blocks with computed styles

```jsx
// components/BlockRenderer.jsx
const blockComponents = {
  'core/paragraph': ({ block }) => (
    <p
      style={block.inlineStyles}
      className={block.classNames.join(' ')}
      dangerouslySetInnerHTML={{ __html: block.innerHTML.replace(/<\/?p[^>]*>/g, '') }}
    />
  ),
  'core/group': ({ block, children }) => (
    <div style={block.inlineStyles} className={block.classNames.join(' ')}>
      {children}
    </div>
  ),
};

export default function BlockRenderer({ blocks }) {
  return blocks.map((block, i) => {
    const Component = blockComponents[block.name];
    if (!Component) {
      // Fallback: WordPress-rendered HTML already contains inline styles.
      return <div key={i} dangerouslySetInnerHTML={{ __html: block.innerHTML }} />;
    }
    return (
      <Component key={i} block={block}>
        {block.innerBlocks?.length > 0 && <BlockRenderer blocks={block.innerBlocks} />}
      </Component>
    );
  });
}
```

```jsx
// app/[slug]/page.jsx
export default async function Page({ params }) {
  const { slug } = await params;
  const res = await fetch(
    `${process.env.NEXT_PUBLIC_WP_URL}/wp-json/wp/v2/pages?slug=${slug}`,
    { next: { revalidate: 60 } },
  );
  const [page] = await res.json();
  return <BlockRenderer blocks={page.block_data} />;
}
```

### Tip: core block base styles

The global stylesheet does not include the core block library CSS. For full parity install `@wordpress/block-library` and import its stylesheets, or copy `wp-includes/css/dist/block-library/style.css` from your WordPress site.

## Filters

| Filter | Purpose |
| --- | --- |
| `headless_block_styles_block_inline_styles` | Modify a block's computed style object. Args: `$styles`, `$attrs`. |
| `headless_block_styles_block_class_names` | Modify a block's computed class names. Args: `$classnames`, `$attrs`. |
| `headless_block_styles_post_types` | Which post types get the `block_data` field. |
