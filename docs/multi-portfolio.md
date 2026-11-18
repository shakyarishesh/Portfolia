# Multi-portfolio admin guide

One installation serves many public portfolios at `/{username}`. Each portfolio has its own content
(profile, projects, posts, sections, uploads, messages…) and picks one of the registered templates.
All templates read the same content, so switching templates never changes or deletes content.

## Setup

```bash
php artisan migrate          # creates portfolios; existing content moves into one portfolio
php artisan portfolio:admin you@example.com   # create an admin user (no public registration)
php artisan test             # run the test suite
```

No asset build is needed for the public templates: their CSS/JS lives in `public/templates/{key}/`.

## Managing portfolios (Admin → Portfolios)

- **List**: search by name, username or owner (name/email); filter by template or status.
  Row actions: edit, preview, switch to, publish/unpublish, archive, delete.
- **Create**: display name, username, optional owner (an existing user), status, template, SEO title,
  description and social image URL. The new portfolio starts with an empty profile and the default
  sections, and becomes the portfolio the content screens edit. Fill it in from
  *Personal info & CV*, *Projects* and the other content screens.
- **Which portfolio am I editing?** The sidebar switcher shows the current portfolio and its public
  URL. Profile, Sections, Projects, Posts, Messages, Imports… all work on that one portfolio only.
  *Sections & order* controls section visibility and order for the switched-to portfolio.
- **Status**: `draft`, `published`, `disabled`, `archived`. Only `published` is public; anything else
  is a 404 for visitors. Admins (and the owner) can still view it with a yellow PREVIEW banner.
  `published_at` is set the first time a portfolio is published.

## Templates: choose → preview → publish

1. On the portfolio's edit page, click **Choose** on a template card. It is saved as a *draft* — the
   live site does not change.
2. **Preview ↗** opens the public page with that template, for you only (PREVIEW banner, `noindex`).
   **Mobile** opens the same preview in a phone-sized window. **Exit** in the banner ends the preview.
3. **Publish template** makes the draft live. **Discard draft** drops it.

Template-specific settings (accent colour, theme…) appear under the details form for the draft
template, or the live one if there is no draft. They are stored per template, so settings for other
templates are kept. Only settings declared in the template's schema are stored.

## Usernames

- 3–40 characters: lower-case letters, digits and single hyphens; no leading/trailing hyphen.
  Input is lower-cased; uniqueness is case-insensitive.
- Reserved: system words (`admin`, `api`, `login`, `blog`, `preview`, `storage`, …, see
  `App\Support\Username::RESERVED`), the first segment of every registered route, and every top-level
  file/folder in `public/`.
- **Changing a username invalidates the old URL.** There is no alias/redirect table; old links 404.

## Deleting

Delete is on the portfolio's edit page and requires typing the username. It permanently removes the
portfolio and, through `ON DELETE CASCADE`, all of its content (profile, projects, posts, sections,
messages, imports, audit history), and deletes its uploaded files. To hide a portfolio but keep it,
set the status to **Archived** instead.

## Adding a template

1. Add an entry under `templates` in `config/portfolio.php`: `name`, `description`, `preview`
   (thumbnail path under `public/`), `active`, `version`, `sections`, and a `settings` schema
   (`type`: `boolean` | `select` | `text` | `textarea`, with `label`, `default`, and `options` for selects).
2. Create `resources/views/portfolios/templates/{key}/{home,project,posts,post}.blade.php`.
3. Put its CSS/JS and `preview.svg` (or an image) in `public/templates/{key}/`.
4. Run `php artisan test` — `MultiPortfolioTest` renders every page of every registered template.

Template keys from the database are only looked up in this registry; view names are never built from input.
