# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) and other coding agents when working with code in this repository.

## Project Overview

Disciple.Tools - Autolink is a WordPress **plugin** that requires the Disciple.Tools theme (>= 1.35). It gives downstream leaders a stripped-down, mobile-first UI — reached through a magic link, not wp-admin — for registering, creating/editing groups ("churches"), reporting health metrics, and viewing their group tree / gen map.

It does not register post types or fields of its own. Everything reads and writes the theme's `groups` and `contacts` post types through `DT_Posts`.

### The `2.0` branch — do not merge it

`origin/2.0` is an abandoned from-scratch rewrite onto CodeZone's plugin framework, with
**no shared history** with `master` (`git merge-base` returns nothing, so `cherry-pick`
does not work). We are not adopting that framework; 2.0 is a parts donor only.
See [`docs/2.0-port-candidates.md`](docs/2.0-port-candidates.md) for the feature port
list, verified commit hashes, and the 2.0 → master path mapping.

## Commands

```bash
npm install                      # install JS deps
composer install                 # install PHPCS + WPCS (dev)

npm run dev                      # build dist/ (development)
npm run prod                      # build dist/ (production, minified)
npm run watch                     # mix watch + BrowserSync (needs .env with MIX_URL, see .env.example)

npm run lint                     # vendor/bin/phpcs (whole repo, per phpcs.xml)
./test/test_phpcs.sh             # same, as CI runs it
./vendor/bin/phpcbf              # auto-fix PHPCS violations
./test/test_for_syntax_errors.sh # php -l over every non-vendor PHP file
```

`dist/` is gitignored and is built by the release workflow. After changing anything under `magic-link/` or `admin/*.js`, run `npm run prod` — the PHP enqueues `dist/magic-link.js`, `dist/magic-link.css`, and `dist/admin.js` directly, and `filemtime()` on those files will fatal if they're missing.

### PHPUnit

Tests need the WordPress test library plus a checkout of the DT theme. `test/bootstrap.php` reads `WP_TESTS_DIR`, `WP_CORE_DIR`, `WP_THEME_DIR`, and `WP_PLUGIN_FILE`, registers the theme, and requires the plugin file.

```bash
./test/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host]
vendor/bin/phpunit
vendor/bin/phpunit --filter test_plugin_installed   # single test
```

Test files must be named `unit-test-*.php` under `test/` to be picked up (see `phpunit.xml.dist`).

### Release

`.github/workflows/release.yml` fires on a tag push (`*.*`): it runs `npm run prod`, `composer install --no-dev`, then **copies an explicit list of paths** into the zip. A new top-level directory will be silently missing from releases unless it is added to that `cp -r` line. Version numbers live in three places and must be bumped together: `disciple-tools-autolink.php` header, `package.json`, `version-control.json` (which is also the update-checker manifest).

## Architecture

### Bootstrap

`disciple-tools-autolink.php` hooks `after_setup_theme` (priority 20), bails unless `Disciple_Tools` exists at the required version, then the `Disciple_Tools_Autolink` singleton `require`s every controller, query, chart, and magic-link class. There is no autoloader and no namespacing — classes are `Disciple_Tools_Autolink_*` and files are included in dependency order, so a new class must be added to that constructor.

### Three magic-link apps, one root

All three extend the theme's `DT_Magic_Url_Base` and share the root `autolink`:

| File | type | URL | Purpose |
|---|---|---|---|
| `magic-link/login-app.php` | `page` | `/autolink` | Public login/register. Opens `dt_blank_access` and `dt_allow_non_login_access`. If already logged in → activates and redirects to the app. |
| `magic-link/user-app.php` | `app` | `/autolink/app/{key}` | The authenticated app. `post_type` is `user`. |
| `magic-link/contact-app.php` | `share` | `/autolink/share/{key}` | A leader's shareable link: sets the `dt_autolink_leader_id` cookie, then redirects to `/autolink`. |

The cookie is the coaching-attribution mechanism: `Magic_Functions::add_session_leader()` runs after login/registration, reads it, and sets `coached_by` + `assigned_to` on the new user's contact record if they have no coach yet.

### Routing and controllers

Routing is a hand-rolled `switch` on `$_GET['action']` × `$_SERVER['REQUEST_METHOD']` inside each app's `routes()`, called from the `dt_blank_body` action. Adding a screen means: a `case` in `routes()`, a method on a controller, and a template.

`user-app.php` GET actions: `survey`, `edit-group`, `create-group`, `delete-group`, `genmap`, `tree`, `logout`, `training`, `group`, default → app. POST: `survey`, `create-group`, `edit-group`. The default GET path force-redirects to `?action=survey` until `survey_completed()` is true.

Controllers extend `Disciple_Tools_Autolink_Controller`, which supplies `$this->functions` (the magic-functions singleton), `$this->settings`, and `global_data()` — the shared label/link/contact bundle. The pattern throughout is: build local variables, `extract( $this->global_data() )`, then `include __DIR__ . '/../templates/x.php'`. Controllers **echo**; they don't return markup. Templates in `templates/` compose shared fragments from `templates/parts/` (`app-header.php`, `app-greeting.php`, `church-view-tabs.php`, `app-footer.php`).

### REST

`user-app.php::add_endpoints()` registers exactly two routes — `GET` and `POST` on `autolink/v1/app` — and dispatches on a `action` body/query param to controller methods (`tree`, `onItemDrop`, `update_field`, `parent_group_field`, `groups`). Permission is `DT_Magic_URL::verify_rest_endpoint_permissions_on_post()`, so requests must carry the magic-link `parts`. New endpoints belong in those switches, not as new `register_rest_route` calls.

`Field_Controller::update()` is the generic inline-edit endpoint. It parses the DOM id as `{post_type}_{id}_{field}` and **only** writes fields returned by the `autolink_updatable_group_fields` filter. A new inline-editable field must be added there or the write is rejected.

### Queries and charts

`queries.php` (`dt_autolink_queries()`) holds two large raw `$wpdb` queries — `tree( 'groups' )` and `tree( 'coaching' )` — that flatten group/contact hierarchies with health-metric existence checks in one pass. They bypass `DT_Posts` for performance, so permission filtering is not applied by the query itself.

`charts/groups-tree.php` builds the drag-and-drop tree (jQuery doMenu; reparenting writes straight to `$wpdb->p2p` inside a transaction in `Tree_Controller::process()`). `charts/groups-genmap.php` is loaded only when the separate DT Genmapper plugin is present — genmap routes degrade to a redirect when `DT_Genmapper_Groups_chart` is missing.

### Frontend

Laravel Mix (`webpack.mix.js`) builds three entries into `dist/`: `magic-link/magic-link.js`, `admin/admin.js`, `magic-link/magic-link.css`.

JS components are Lit elements extending `DtBase` (or a concrete component such as `DtNumberField`) from `@disciple.tools/web-components`, registered with `window.customElements.define`, and most override `createRenderRoot()` to return `this` (light DOM) so page CSS applies. Declare reactive properties with `static get properties()` — decorators are **not** available; the `@babel/plugin-proposal-decorators` plugin was removed when the plugin moved to lit 3.

**`@disciple.tools/web-components` is not bundled.** `webpack.mix.js` marks it `external` as the global `DtWebComponents`, which is the copy the theme enqueues (handle `web-components`, `dt-assets/build/components/index.js`). The plugin therefore always runs against whatever version the installed theme ships, and `lit` must stay on the major the theme's library uses (3.x). Two consequences to remember:

- `web-components` and `web-components-css` must stay on the magic-link allow lists in `magic-link/functions.php`, and `web-components` is a declared dependency of `magic_link_scripts` so it loads first.
- Library APIs are not ours to pin, and 1.0 removed several the plugin depended on:
  - **`dt-button` no longer supports `href` or `confirm`**, and its click handler calls
    `preventDefault()` unconditionally. `magic-link/js/button-links.js` restores link
    behaviour with one delegated `document` click listener. Wrapping a `dt-button` in an
    `<a>` does **not** work — the component's `preventDefault()` cancels the anchor too.
    `rounded` was also renamed to `round`.
  - **`dt-modal` lost its `openButton` slot**, and its header/footer chrome is not
    reachable from outside its shadow root (no `part` attributes). `app-church-counts`
    therefore uses a plain `<dialog>` styled from `css/_churches.css`.
  - `dt-copy-text` used to offset its copy icon `-0.3125em` vertically while its flex
    container already centred it, leaving the icon ~4px high. **Fixed upstream** in
    `@disciple.tools/web-components`; needs a release past 1.0.2. The `--dt-form-padding`
    override in `css/_fields.css` is a separate concern — it reserves horizontal room so
    the link text doesn't run under the icon, which the library still doesn't do.

  - **`dt-button` does not submit forms.** Its click handler dispatches a `submit`
    *event* at the form, which notifies listeners but submits nothing. `submit-button`
    (`magic-link/js/submit-button.js`) overrides `handleClick()` to call
    `form.requestSubmit()`. Any new submit button must use `<submit-button>`, not
    `<dt-button type="submit">`.

Known noise, not a plugin bug: the theme's Foundation bundle (`site-js`) listens for
`close` and `open` on `document`, and those are also the names a native `<dialog>` and
`dt-modal` dispatch, so every open/close logs `'close' is not an available method for this
element`. Its listeners are registered before anything the plugin can hook, so it cannot be
intercepted; the only fix would be not loading `site-js` at all, which was tried and
rejected — Foundation is wanted everywhere or nowhere, and nothing actually breaks.

### The location field

The group form renders `location_grid_meta` through the theme's
`render_field_for_display()`, which emits a **`<dt-location-map>` web component**. It is a
form associated custom element, so it submits its own value under its field name as a JSON
array — no proxy input, and none of the theme's legacy jQuery mapbox search widget
(`DT_Mapbox_API::load_mapbox_search_widget()`), which is deliberately not enqueued.

`Group_Controller::process()` decodes `$_POST['location_grid_meta']`. An untouched
component posts `null` and one the user has emptied posts `[]`, so **only an array is
treated as an instruction to write the field** — otherwise a save would silently wipe
existing locations. `clean_location_values()` then keeps only the keys DT stores, because
the component passes the geocoder's response straight through (Google predictions arrive
with a nested `raw` blob).

The component does **not** geocode by itself — see ComponentService below.

PHP → JS data flows two ways: `wp_localize_script('magic_link_scripts', 'app'|'magic', ...)` for URLs, nonces, and translations; and JSON-in-attributes on components, e.g. `posts='<?php echo esc_attr( wp_json_encode( $churches['posts'] ) ); ?>'`.

`magic-link/magic-link.css` sets the whole DT web-component CSS custom-property theme at `html` scope, then imports `css/__index.css`, which pulls in the `_*.css` partials. Restyling components means overriding `--dt-*` variables there, not reaching into shadow DOM.

### The leaders field

`leaders` on the group form is a **`dt-connection`** with **static** options: the user's own
contact plus everyone in their coaching tree (`Group_Controller::form()`). `dt-connection`
filters a non-empty `options` list locally by label and never calls the API, so it does not
need a `dt:get-data` handler — and the picker stays scoped to the coaching tree rather than
every contact the user can see.

Two shape rules that are easy to get wrong:

- **Ids must be integers, not numeric strings.** `_remove()` parses the clicked id with
  `Number.parseInt` and compares with `===`, so a string id never matches and the chip's
  "x" silently does nothing. Ids typed by the user (`allowAdd`) stay strings, which is fine
  — they never parse to a number.
- The value is `[ { id, label } ]`, and removals come back **flagged** `delete: true`
  rather than dropped, so `process()` filters them out before building the connection
  values. A non-numeric id is a new contact to create (see `allowAdd`).

### ComponentService

Several library components do not talk to the API themselves. They dispatch an event and
wait for the page to answer: `dt-location-map` fires `dt:geocode` for its address search,
and `dt-connection` / `dt-tags` / `dt-location` fire `dt:get-data` to load options. With
nothing listening they simply come up empty — which is why the location search returned no
results until this was wired.

`magic-link/js/component-service.js` runs the library's own handler, the same way the theme
does on its screens (`dt-assets/js/new-record.js`):

```js
const service = new ComponentService( 'groups', '', window.app.nonce, window.app.rest_base );
service.initialize();
```

**The empty post id is deliberate.** `initialize()` only calls `enableAutoSave()` when it
has one, and auto-save would make every field POST straight to `dt-posts/v2/…` on change —
wrong for Autolink twice over: the group form posts on submit, and magic link users are low
privilege, so writes go through the whitelisted `update_field` endpoint instead.

### Admin and settings

`admin/admin-menu-and-tabs.php` adds a submenu under `dt_extensions` gated on `manage_dt`, delegating to `Admin_Controller` → `templates/admin/`. `admin/settings.php` is the option layer: `defaults()` is the single source of truth, `setup_options()` seeds missing options on instantiation, and `get_option()` falls back to the default. Add new settings there — including the Vimeo training-video list, which is stored as a JSON option but seeded from localized defaults.

The admin page has two tabs, each a separate form saved by its own controller: **General** (`controllers/admin-general.php`, grouped into the Disciple.Tools menu / the Autolink app menu (training, survey) / groups / contacts) and **Training Videos** (`controllers/admin-training.php`). A tab's `save()` must only write the options its own form posts — an unchecked box posts nothing, so a shared save would reset the other tab's checkboxes.

**Contacts are a switch, off by default** (`disciple_tools_autolink_enable_contacts`, `Settings::contacts_enabled()`), because they arrived after the plugin's first release. Off means: `contact-link-app.php` is never loaded (so the theme neither serves contact links nor lists one on the Apps tile), `Actor::may_act_as()` refuses the `contact` type, the Contacts tab is hidden, and the contacts/upgrade routes and the `contact_link` REST action are closed in `app-routes.php`. Anything new that belongs to contacts needs the same check.

## Conventions and gotchas

- **Text domain is `disciple-tools-autolink`**, not the theme's `disciple_tools`. A handful of mapbox strings deliberately reuse `disciple_tools` to inherit theme translations.
- **"Church" is UI vocabulary for the `groups` post type.** Labels are pulled from `get_post_type_labels( get_post_type_object( 'groups' ) )` so copy follows the site's configured naming — don't hardcode "Church"/"Group" in new strings; concatenate the label.
- **Nonces are per-form and named**: `dt_autolink_group` (`Group_Controller::NONCE`), `dt_autolink_login`, `dt_autolink_register`, `dt_autolink_survey`, `dt_autolink_delete_group`, plus `wp_rest` on `Group_Controller::index()`.
- **Magic-link activation** (`Magic_Functions::activate()`) writes the `{$wpdb->prefix}autolink_app_magic_key` user meta and the `autolink-app` user option (and the `share` pair) on first login. `is_activated()` only checks the `app` pair.
- `user-app.php::user_has_cap()` force-grants `view_any_contacts` for the duration of a magic-link request — leaders are typically low-privilege users.
- Extension points this plugin exposes: filters `dt_autolink_survey`, `autolink_updatable_group_fields`, `autolink_health_fields`; actions `dt_autolink_group_created`, `dt_autolink_group_updated`.
- **Indentation is inconsistent** (tabs in most `controllers/` and `magic-link/functions.php`, 4 spaces in others). `phpcs.xml` excludes the space/tab indent sniffs, so match the file you are editing rather than reformatting it, and let `./test/test_phpcs.sh` be the authority.
