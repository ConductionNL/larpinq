// @ts-check

/**
 * LarpingApp documentation site.
 *
 * Built on @conduction/docusaurus-preset for brand defaults (tokens,
 * theme swizzles for Navbar / Footer, i18n scaffolding, KvK / BTW
 * copyright). Site-specific overrides — locale (en only), sidebar
 * path, mermaid theme, custom prism themes, larpingapp-only navbar
 * items — are passed through createConfig() opts.
 *
 * Journeydoc scaffold (ADR-030): tutorials live under
 * `docs/tutorials/{user,admin}/`, screenshots under
 * `docs/static/screenshots/tutorials/{user,admin}/`, capture spec at
 * `tests/e2e/docs-screenshots.spec.ts`.
 */

const { createConfig, baseFooterLinks } = require('@conduction/docusaurus-preset');

/* createConfig replaces themes wholesale when `themes:` is passed, so
   we re-include the brand theme plugin alongside @docusaurus/theme-mermaid.
   Without the brand theme entry the Navbar/Footer swizzles and
   brand.css auto-load would silently drop. */
const BRAND_THEME = require.resolve('@conduction/docusaurus-preset/theme');

const config = createConfig({
  title: 'LarpingApp',
  tagline: 'Characters, rules, scenes, and NPC stats for live-action role-play. Build a setting in Nextcloud and run sessions without spreadsheets-with-six-tabs.',
  url: 'https://larpingapp.conduction.nl',
  baseUrl: '/',

  organizationName: 'ConductionNL',
  projectName: 'larpingapp',

  /* English-only for now (ADR-030 / journeydoc). The brand preset
     ships a multi-locale i18n block (nl/en/de/fr); enabling 'nl' here
     without a complete `i18n/nl/docusaurus-plugin-content-docs/current/`
     translated-markdown tree breaks SSR on doc pages — stale locale
     metadata trips `Cannot read properties of undefined (reading 'id')`.
     The previous config carried `locales: ['en', 'nl']` plus an
     `i18n/nl/` dir holding only `current.json` (no translated markdown),
     which is exactly that failure mode. Re-add 'nl' once a real Dutch
     translation pass has shipped the translated-markdown tree. */
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
    localeConfigs: {
      en: { label: 'English' },
    },
  },

  /* The larpingapp docs source lives at the repo root of `docs/`
     rather than under a `docs/` subfolder, so we override the preset's
     default `presets:` block to point `docs.path` at './' and disable
     the blog plugin. customCss carries larpingapp-specific CSS only —
     brand tokens and the theme swizzles are auto-loaded by the brand
     theme entry in `themes:` below. */
  presets: [
    [
      'classic',
      {
        docs: {
          path: './',
          /* docs.path: './' makes plugin-content-docs scan every file
             in docs/, which collides with plugin-content-pages's own
             scan of docs/src/pages/. Exclude src/ (pages live there)
             plus the standard node_modules bucket.

             Also excludes a handful of feature-reference files that
             contain raw `{token}` patterns the MDX 3 parser interprets
             as JSX expressions (e.g. `{type}_register`,
             `{characterName}_character_sheet.pdf`,
             `/characters/{uuid}`). They pre-date this preset migration
             and are not user-facing landing pages; leaving them out of
             the scan is the lowest-risk fix. Track properly in a
             follow-up MDX-cleanup PR. */
          exclude: [
            '**/node_modules/**',
            'src/**',
            'features/object-service.md',
            'features/pdf-export.md',
            'features/deep-link-registration.md',
          ],
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: 'https://github.com/ConductionNL/larpingapp/tree/development/docs/',
        },
        blog: false,
        theme: {
          customCss: require.resolve('./src/css/custom.css'),
        },
      },
    ],
  ],

  themes: [BRAND_THEME, '@docusaurus/theme-mermaid'],

  /* Brand navbar provides locale dropdown + GitHub by default; we
     replace items[] with larpingapp's own (Documentation sidebar link,
     larpingapp GitHub link, locale dropdown). */
  navbar: {
    items: [
      {
        type: 'docSidebar',
        sidebarId: 'tutorialSidebar',
        position: 'left',
        label: 'Documentation',
      },
      {
        href: 'https://github.com/ConductionNL/larpingapp',
        label: 'GitHub',
        position: 'right',
      },
      { type: 'localeDropdown', position: 'right' },
    ],
  },

  /* Per-property footer override (preset 1.2.0+): we pass `links` only,
     so the brand `style: 'dark'` and the brand KvK/BTW/IBAN/address
     copyright string both inherit unchanged. Single-column brand
     "Conduction" anchor pulled from baseFooterLinks(). */
  footer: {
    links: [
      ...baseFooterLinks().filter((column) => column.title === 'Conduction'),
    ],
  },

  /* Drop the canal-footer's boat-sinking + kade-cyclist mini-games
     on this product-page footer (preset 1.3.0+). The static skyline +
     canal decoration are kept; the interactive layer goes away. */
  minigames: false,

  /* themeConfig is shallow-merged into the preset's defaults
     (colorMode + navbar + footer). prism + mermaid land alongside. */
  themeConfig: {
    prism: {
      theme: require('prism-react-renderer/themes/github'),
      darkTheme: require('prism-react-renderer/themes/dracula'),
    },
    mermaid: {
      theme: { light: 'default', dark: 'dark' },
    },
  },
});

/* createConfig doesn't pass-through arbitrary top-level fields; assign
   markdown + onBroken* directly so they make it into the final
   Docusaurus config. */
config.onBrokenLinks = 'warn';
config.onBrokenMarkdownLinks = 'warn';
config.onBrokenAnchors = 'warn';
config.markdown = {
  mermaid: true,
  /* Tutorial pages under `tutorials/{user,admin}/` reference
     screenshots populated by `tests/e2e/docs-screenshots.spec.ts`. The
     Playwright capture run is separate from the docs build, so the
     build must succeed even when a fresh checkout doesn't have every
     PNG yet (the journeydoc skeletons ship with TODO bodies, no images
     yet). Warn instead of failing — absence is visible at preview time
     and the capture spec brings everything back on demand. Flip to
     'throw' once screenshots are committed. (ADR-030) */
  hooks: {
    onBrokenMarkdownImages: 'warn',
  },
};

module.exports = config;
