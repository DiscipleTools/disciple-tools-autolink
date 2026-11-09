# Autolink on multisite

How Autolink behaves on a WordPress multisite network, and the rules code has to follow to keep it working there.

## Keep every URL on the current site

On a subdirectory network every site shares the host (`example.com/site2/`, `example.com/site3/`). A root-relative path such as `/autolink` or `/wp-json/...` goes to the root site, not the one the person is on. A form posting to `/autolink?action=login` signs them in to the wrong site's Autolink. A tree fetching `/wp-json/autolink/v1/app` reads another site's data.

- Build links from `get_site_url()` / `site_url()`. `Disciple_Tools_Autolink_Magic_Functions::get_link_url()` is the Autolink login page. Add query arguments with `add_query_arg()`.
- Build REST URLs with `rest_url()`, never `/wp-json/...`.
- Build core asset URLs with `includes_url()`, never `/wp-includes/...`.

## Scope cookies to the site

`dt_autolink_leader_id` holds the coach's contact ID. Contact IDs are per site, so a cookie on `/` would carry one site's coach into another site's registration. Set and clear it on `leader_cookie_path()`, which is the site's own path.

The path alone isn't enough: the root site of a subdirectory network has the path `/`, and every other site receives its cookies. So on multisite the value is `{blog_id}:{leader_id}`, and a site ignores a cookie that names another blog or has no blog ID. Read and write it only through `set_session_leader()` and `get_session_leader_id()`. `add_session_leader()` also skips a leader that doesn't exist on the current site.

## Accounts from another site join automatically

**Decision:** someone who already has an account on another site in the network, and signs in to this site's Autolink, is added to this site with WordPress's `default_role`, the same role the Register form gives a new account (`wp_create_user()` uses it). This also covers someone already signed in elsewhere who opens this site's `/autolink`. The join happens in `join_current_site()`, called from `activate()`. Adding them fires the theme's `add_user_to_blog` hook, which creates their contact.

**This ignores the site's registration setting on purpose.** Autolink is an open sign-up app: its Register form doesn't check `dt_can_users_register()`, and a site that doesn't want open sign-ups won't run Autolink. Letting an existing account join therefore gives no wider access than registering a new one would. The theme's SSO login also adds existing network users to the site they sign in to, but with its own DT login default role, which falls back to `registered` (no permissions). Autolink doesn't use that role, because a joiner with no permissions would land in an app they can't use.

**Why not leave them as they are:** a network login is valid on every site. Without a role here they would get an Autolink app with no contact behind it, so nothing they did in it would work.

**Alternatives considered:**

- *Join only when the site allows registration, otherwise block with a message.* Rejected: it keeps out people the site would let register anyway, and a site with registration closed isn't one that runs Autolink.
- *Hold the join for admin approval.* Rejected for the same reason, and it would need a pending-request store, notifications and an admin screen.

If Autolink ever gets a way for a site to close sign-ups, `join_current_site()` should follow the same rule as the Register form.
