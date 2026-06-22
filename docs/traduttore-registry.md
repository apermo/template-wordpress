# Self-hosted translations (Traduttore Registry)

This is **opt-in scaffolding**. Nothing here is wired into the template's bootstrap — copy the relevant variant
into your project only when you want to deliver translations from a self-hosted GlotPress server.

## Two delivery models

There are two ways to ship translations from a self-hosted GlotPress instance, and they solve different problems:

- **Build-time download** via `inpsyde/wp-translation-downloader` `api.names`: the translations are fetched during
  `composer install` on the host site. Good when *you* control the only install (e.g. one chrdm.de site) and want the
  catalog baked in at deploy time.
- **Runtime delivery** via `wearerequired/traduttore-registry`: WordPress fetches translations at runtime from the
  GlotPress API, exactly like translate.wordpress.org. Works on *any* install, including ZIP/non-Composer ones.

When to use which: chrdm.de-only deployment → `api.names`; you ship to arbitrary installs you do not control → embed
the registry (this doc); publishing on wordpress.org → don't self-host at all, use translate.wordpress.org and drop
both.

This document covers the **runtime** model. The build-time/server side is documented in
[apermo/chrdm.de → `docs/translations.md`](https://github.com/apermo/chrdm.de/blob/main/docs/translations.md).

## Step 1 — Require the library

```bash
composer require wearerequired/traduttore-registry
```

**Bundling vs. plain require.** The registry resolves translations at runtime, so the library must be present wherever
the plugin/theme runs:

- **ZIP / non-Composer installs** (uploaded through the WordPress admin): ship `vendor/` inside the release artifact so
  the library travels with the code. Make sure your build (`.gitattributes` `export-ignore` rules, release workflow)
  includes `vendor/` in the ZIP.
- **Composer-managed hosts** (Bedrock and similar): a plain `require` in `composer.json` is enough — the host's
  autoloader provides the library, and you should *not* bundle `vendor/`.

Either way, guard the call with `function_exists()` so a missing library degrades to a no-op instead of a fatal error.
A site without the registry simply falls back to whatever translations are already installed (or English).

## Step 2 — Register the project on `init`

Pick the variant that matches your mode. Both register the project against the GlotPress translations API. The trailing
slash on the API URL is **significant** — the bare `/api/translations/` path returns a 404.

Replace `<translate-host>` with your GlotPress host (apermo's instance is `translate.chrdm.de`). The slug and text
domain below use this template's placeholders, so `setup.sh` substitutes them per project automatically. Adjust the
`namespace` line to your project's own namespace — in a Markdown file `setup.sh` only rewrites the placeholder to its
underscore form, not the backslashed namespace your PHP actually uses.

### Plugin variant

Create `src/I18n.php`:

```php
<?php

declare(strict_types=1);

namespace Plugin_Name;

\defined( 'ABSPATH' ) || exit();

use function Required\Traduttore_Registry\add_project;

/**
 * Registers the plugin with the self-hosted Traduttore Registry so installs
 * receive translations from the GlotPress server.
 *
 * PHP translations are loaded just-in-time by WordPress 6.4+, so no manual
 * `load_plugin_textdomain()` call is needed; this class only points WordPress
 * at the translation source.
 */
final class I18n {

	/**
	 * Project type as understood by Traduttore Registry.
	 */
	private const PROJECT_TYPE = 'plugin';

	/**
	 * GlotPress translations API endpoint for this project. The trailing slash
	 * is significant; the bare `/api/translations/` path returns a 404.
	 */
	private const API_URL = 'https://<translate-host>/glotpress/api/translations/plugin-name/';

	/**
	 * Wires the registration on init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_project' ] );
	}

	/**
	 * Registers the project with Traduttore Registry when the library is
	 * present. Degrades to a no-op when the dependency is missing.
	 *
	 * @return void
	 */
	public function add_project(): void {
		if ( \function_exists( 'Required\Traduttore_Registry\add_project' ) ) {
			add_project(
				self::PROJECT_TYPE,
				'plugin-name',
				self::API_URL,
			);
		}
	}
}
```

Wire it in `Main::boot()`:

```php
public static function boot(): void {
	( new I18n() )->register();
}
```

### Theme variant

Create `src/I18n.php`:

```php
<?php

declare(strict_types=1);

namespace Plugin_Name;

/**
 * Registers the theme with the self-hosted Traduttore Registry so installs
 * receive translations from the GlotPress server.
 *
 * This class only points WordPress at the translation source.
 */
class I18n {

	/**
	 * Project type as understood by Traduttore Registry.
	 */
	private const PROJECT_TYPE = 'theme';

	/**
	 * GlotPress translations API endpoint for this project. The trailing slash
	 * is significant; the bare `/api/translations/` path returns a 404.
	 */
	private const API_URL = 'https://<translate-host>/glotpress/api/translations/plugin-name/';

	/**
	 * Registers the project with Traduttore Registry when the library is present.
	 *
	 * Degrades to a no-op when the dependency is missing.
	 *
	 * @return void
	 */
	public static function add_project(): void {
		if ( \function_exists( 'Required\Traduttore_Registry\add_project' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions, SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions, SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly -- The ruleset also bans `use function` imports, so a guarded FQ call is the only option.
			\Required\Traduttore_Registry\add_project(
				self::PROJECT_TYPE,
				'plugin-name',
				self::API_URL,
			);
		}
	}
}
```

Wire it on `init` from `Theme::init()`:

```php
public static function init(): void {
	add_action( 'init', [ I18n::class, 'add_project' ] );
	// ...existing hooks
}
```

> The `phpcs:ignore` above is only needed under `apermo/apermo-coding-standards` **< 3.0**, whose ruleset bans both
> `use function` imports and fully-qualified global calls, leaving a guarded FQ call as the only option. On 3.0+ you
> can use the `use function Required\Traduttore_Registry\add_project;` form shown in the plugin variant instead.

## Complete the setup

A few things make the catalog actually load and stay reproducible:

- **JIT loading.** On WordPress 6.4+ PHP strings load just-in-time, so you do **not** need a manual
  `load_plugin_textdomain()` / `load_theme_textdomain()` call. The `I18n` class above is the whole wiring.
- **`Domain Path` header.** Add `Domain Path: /languages` to the plugin header (next to `Text Domain`) so WordPress
  knows where bundled catalogs live. The template's `plugin.php` already ships this header.
- **Generate the catalog.** Add a `make-pot` script to `composer.json` and run it to (re)generate the template:

  ```json
  "make-pot": "wp i18n make-pot . languages/plugin-name.pot --domain=plugin-name --exclude=vendor,tests,e2e,.ddev,node_modules"
  ```

- **Gitignore the generated catalog.** The `.pot`/`.po`/`.mo` files are a [generated asset](#generate-the-catalog),
  built from source and (in the runtime model) delivered by GlotPress — keep them out of version control with a
  `languages/.gitignore`:

  ```gitignore
  *
  !.gitignore
  ```

- **Editor-block JS.** For any block/editor JavaScript, call `wp_set_script_translations()` so JS strings resolve
  against the same text domain:

  ```php
  wp_set_script_translations(
      self::EDITOR_SCRIPT_HANDLE,
      'plugin-name',
      \dirname( Main::file() ) . '/languages',
  );
  ```

  Note: the `make-pot` JS extractor matches the literal `__` / `_x` callee, not aliases — if you import the i18n
  functions under a different name, the extractor will miss those strings.

## See also

- [apermo/chrdm.de → `docs/translations.md`](https://github.com/apermo/chrdm.de/blob/main/docs/translations.md) —
  GlotPress server setup, project creation, and the build-time `api.names` deployment reference.
- [`wearerequired/traduttore-registry`](https://github.com/wearerequired/traduttore-registry) — library README.
