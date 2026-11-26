# Portfolio app (Laravel)

The dynamic version of the portfolio. Everything on the public site is stored in MySQL and edited from an admin panel. The old static site lives in [`../static-site`](../static-site) and is not touched by this app.

- **Public site:** `/`. This is the tabbed one-page portfolio, with the same look, three.js background, intro and cursor as the static version. It also has project search and filters, light/dark mode with four accent colours, an interactive terminal (press <kbd>`</kbd>), and an optional AI assistant.
  - Each project gets its own case-study page at `/projects/{slug}`, with an optional Mermaid architecture diagram and related projects.
  - `/blog` is the engineering journal (Markdown articles with a table of contents and code highlighting).
  - `/playground` is a sandboxed REST API demo with sample data only.
  - The CV downloads from `/resume`, and the published content is also available as read-only JSON at `/api/portfolio`.
  - `/sitemap.xml` and `/robots.txt` are generated from the published content.
- **Portfolio types:** technical, creative, business, management, education, general and hybrid portfolios, each with its own project fields, sections and recommended templates. See [docs/portfolio-types.md](docs/portfolio-types.md).
- **Admin:** `/admin`. It covers content CRUD, publish and reorder, uploads, blog articles, testimonials, CV versions, messages, analytics, the conversation import and review screens, and the audit history.

Stack: Laravel 12, PHP 8.2+, MySQL 8, Blade, and plain CSS/JS. There's no Node build step. The official Anthropic PHP SDK is only used when AI extraction is turned on.

---

## Local setup

```bash
cd portfolio-app
cp .env.example .env              # already present in this checkout
composer install
php artisan key:generate

docker compose up -d mysql        # MySQL 8.4 on 127.0.0.1:3307 (creates portfolio + portfolio_test)
php artisan migrate --seed        # tables + verified initial content (see docs/profile-inventory.md)
php artisan storage:link
php artisan portfolio:admin you@example.com   # prompts for a password (min 12 chars)

php artisan serve                 # http://localhost:8000  ·  admin: /admin
php artisan queue:work            # only needed for AI extraction
```

If a `portfolio-mysql` container already exists (one was created with `docker run` during development), skip `docker compose up` and use `docker start portfolio-mysql`.

To start over: `php artisan migrate:fresh --seed`.

`composer install` may fail on some Debian/Ubuntu Composer packages because of a crash in the security-advisory check. If it does, use `composer install --no-security-blocking`.

### Environment

| Variable | Purpose |
|---|---|
| `DB_*` | MySQL connection. The defaults match `compose.yaml`. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Optional. The seeder creates this admin. Otherwise use `php artisan portfolio:admin`. |
| `AI_PROVIDER`, `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | Optional AI extraction (see below). Leave empty to switch it off. |
| `PORTFOLIO_IMPORT_MAX_KB`, `PORTFOLIO_IMAGE_MAX_KB`, `PORTFOLIO_RESUME_MAX_KB` | Upload limits. |
| `SEED_RESUME_PATH` | The PDF the seeder attaches as the CV. Defaults to `../rishesh_shakya.pdf`. |
| `MAIL_*` | Needed for contact-form email notifications. The default `log` mailer only writes them to `storage/logs`. |
| `CONTACT_NOTIFY`, `CONTACT_NOTIFY_EMAIL` | Email the owner on each contact message. On by default, and sent to the profile email unless `CONTACT_NOTIFY_EMAIL` is set. |
| `PORTFOLIO_ASSISTANT` | Set to `true` (with `ANTHROPIC_API_KEY`) to show the public “Ask about me” assistant. It is off by default because each question costs API credit. It's limited to 5 questions a minute and 40 a day per IP. |

The keyword list used by the import scan lives in `config/portfolio.php`.

---

## Managing content

| Admin page | What it controls |
|---|---|
| Personal info & CV | Name, headline, rotating hero phrases, intro, about, contact details, SEO description, the profile photo upload (or avatar URL), and contact-form on/off. CVs are versioned: each upload is kept and becomes the active one, you can switch back to any version, and the downloads of each version are counted. |
| Sections & order | The public tabs: their labels, titles, visibility and order. Sections with nothing published are hidden automatically. |
| Experience, Projects, Achievements & milestones, Skill categories, Skills, Technical expertise, Education, Certifications, Professional links | Searchable, filterable, paginated lists. You can create, edit, delete (with confirmation), publish or unpublish, and reorder with ↑/↓. Projects also have a thumbnail, screenshots and a draft preview, plus case-study fields (category, problem, challenges and solutions, a Mermaid diagram, an SEO description). A skill whose name matches a project technology links to those projects. |
| Blog articles | Markdown articles with a slug, excerpt, category, tags, SEO description and cover image. A future publish date schedules the article, and you can preview drafts. Nothing is published until you set it to Published. |
| Testimonials | Quotes from colleagues. Record in the private source note how you got their permission. |
| Messages | Contact-form submissions, with read/unread, replied and archived states, search and an archive view. |
| Analytics | Page views, daily unique visitors, CV downloads, contact conversion, the most-viewed projects and articles, and traffic sources, over 7, 30, 90 or 365 days. No IPs, cookies or user agents are stored; each visitor gets a daily-rotating salted hash, and bots and Do-Not-Track browsers are not counted. |
| Audit history | Every create, update and delete, showing who made it, from which source, and the before and after values. You can restore any entry. |

Every content record has a **status**: `draft`, `needs_review` or `published`. Only `published` records reach the public site or the API. Each record also has a private **source / evidence** note that is never shown publicly.

Intro, about, summaries and list items accept `**bold**` and `[links](https://…)`. Everything else is escaped, and `javascript:` links are stripped.

---

## Updating the portfolio from Claude conversations

1. **Import:** go to *Admin → Import conversations*. You can upload any of these:
   - a **claude.ai data export**: Settings → Privacy → Export data, then upload the `.zip` or `conversations.json`
   - a **Claude Code session**: `~/.claude/projects/<project>/<session>.jsonl`
   - a `.txt` or `.md` transcript, or pasted text with `Human:` / `Assistant:` prefixes

   Files are type-checked and size-limited. Tool output and harness boilerplate in Claude Code transcripts are dropped, and very long conversations are capped (the screen tells you when that happens).
2. **Keyword scan (always on):** this scans only *your* messages for technology names and GitHub/LinkedIn profile links, and proposes skills and links. It doesn't try to understand context. The assistant's suggestions are ignored.
3. **AI extraction (optional):** select up to 10 conversations and run it. Claude proposes updates to projects, roles, achievements, education and so on. Each proposal includes a verbatim excerpt from your own messages, a confidence score and a rationale.
4. **Manual proposals:** open any conversation, paste an excerpt, and propose a change yourself. This works without AI.
5. **Comparison:** each proposal is matched against existing records, by name, title, company, institution or URL, and classified as one of:
   - *New*: nothing matching exists
   - *Adds detail*: it fills empty fields or appends list items (lists are merged, never replaced)
   - *Contradiction*: it disagrees with a value you already have
   - *Duplicate*: it adds nothing
6. **Review:** go to *Admin → Review changes*, which lists contradictions first. Each proposal shows the current values next to the proposed ones, plus the evidence. You can **approve**, **edit** (it is re-classified after editing) or **reject** it, and bulk-dismiss duplicates.
   - Contradictions need an explicit "overwrite existing values" tick.
   - Approved changes go through the same validation as the admin forms.
   - New records are created as **drafts** by default.
7. **Audit:** every applied change is logged with `source = import` and the proposal ID, so it can be restored from *Audit history*.

Nothing is applied automatically, and re-importing the same conversation does not create duplicate proposals.

### Turning on AI extraction

```dotenv
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-5-5   # default
```

Then run a queue worker: `php artisan queue:work`.

- **Where the key is used:** only on the server, in `App\Services\Import\Extractors\AnthropicExtractor`. It is never sent to the browser.
- **What is sent:** only the conversations you select.
- **What the API returns:** structured JSON against a schema. Long conversations are split into chunks rather than truncated.
- **When AI is off:** the import page says so, and the AI button doesn't render.
- **Adding another provider:** implement `LlmExtractor` and add a `match` arm in `AppServiceProvider`.

---

## Tests

```bash
php artisan test
```

The tests run against the `portfolio_test` MySQL database (see `phpunit.xml`), and the suite currently has 63 tests. It covers:

- public visibility rules, section order, SEO tags and the API
- auth and rate limiting
- CRUD with validation, reordering, publishing, the audit log and restore
- upload validation (no SVG, PDF-only CV served privately)
- the contact form and its honeypot
- every import parser
- keyword-scan classification, approving, rejecting, editing, contradiction confirmation, list merging and the AI job
- the Anthropic extractor against a mocked HTTP transport
- the blog: drafts and scheduling, escaping, the table of contents, search and tags, related articles, admin create and preview
- the sitemap and robots.txt
- the demo API (filters, 404 and 422 responses, the payroll arithmetic) and the playground page
- the assistant: off by default, and when on it sends only published content to a mocked model
- privacy-safe page views, CV versions and download tracking, analytics, message states, and the contact email

---

## Security notes

- Admin routes require an authenticated user with `is_admin`. There is no public registration. Login is rate limited to 5 attempts per minute per email and IP, and the session is regenerated on login.
- CSRF is on for every form. All input is validated on the server. Every response gets `nosniff`, `X-Frame-Options: DENY` and `Referrer-Policy` headers, and admin pages also get `noindex` and `no-store`.
- Uploads are restricted by content type, not just extension:
  - Images: JPG, PNG, WebP or GIF, with SVG rejected.
  - CV: PDF only, stored on the private `local` disk and streamed through `/resume`.
  - Stored file names are random.
- The contact form is limited to 5 posts per hour per IP and has a honeypot field.
- The demo API (`/api/demo/*`) is limited to 30 requests per minute per IP. It works only on in-memory sample data, with no database access and no auth.
- The assistant only sees published content, its answers are rendered as plain text, and it's rate limited. It returns 404 unless `PORTFOLIO_ASSISTANT=true`.
- Blog Markdown escapes raw HTML and drops `javascript:` links. Mermaid diagrams render with `securityLevel: 'strict'`.
- The terminal runs only in the browser and reads the public `/api/portfolio` JSON. It can't execute anything on the server.
- No CSP header is set, because the page uses inline bootstrapping scripts and a three.js CDN module. Add one at the web-server level if you need it.

## Deployment

Any PHP 8.2+ host with MySQL works:

1. Point the web root at `portfolio-app/public`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and the `DB_*` values.
3. Run:
   ```bash
   composer install --no-dev -o
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. Create the admin with `php artisan portfolio:admin`.
5. If AI extraction is on, run `php artisan queue:work` under a process manager such as Supervisor or systemd.
6. Configure a real `MAIL_MAILER` if you want contact-form emails. They are sent after the response, so they don't need the queue worker.
7. Content changes show up immediately, with no rebuild or redeploy, because the public pages read the database on every request.

### GitHub Actions (shared hosting)

`.github/workflows/deploy.yml` runs on every push to `production`. It builds the assets, runs the tests, uploads the app over SSH with rsync, then runs `migrate --force` and `optimize` on the host. The server needs SSH, rsync and PHP 8.2+. It doesn't need Node or Composer.

One-time setup:

1. Create a deploy-only key pair on your machine: `ssh-keygen -t ed25519 -f deploy_key -N ""`.
2. Add `deploy_key.pub` to the host's authorized keys (cPanel: **SSH Access → Manage SSH Keys → Import**, then **Authorize**; DirectAdmin: **SSH Keys → Paste key**).
3. In GitHub, go to **Settings → Secrets and variables → Actions** and add these secrets:
   - `SSH_HOST`, `SSH_PORT`, `SSH_USER`
   - `SSH_KEY`: the contents of `deploy_key` (the private half). Delete the local file afterwards.
   - `SSH_KNOWN_HOSTS`: the output of `ssh-keyscan -p <port> <host>`
4. Optionally, add these variables:
   - `DEPLOY_PATH`: the app directory relative to your home. Default: `portfolio`.
   - `PHP_BIN`: the host's PHP 8.2+ CLI, if plain `php -v` shows an older version. Usually `/opt/cpanel/ea-php82/root/usr/bin/php` on cPanel or `/usr/local/php82/bin/php` on DirectAdmin.
5. Create a MySQL database and user in the control panel.
6. Push to `production`, or run the workflow by hand from the **Actions** tab. The first run uploads the code, then stops because `.env` is missing.
7. On the server:
   ```bash
   cd ~/portfolio && cp .env.example .env
   # edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*
   php artisan key:generate
   ```
   Re-run the workflow, then create the admin with `php artisan portfolio:admin you@example.com`.
8. Point the domain's document root at `~/portfolio/public` by replacing `public_html` with a symlink. Keep the old one as a backup:
   ```bash
   mv ~/public_html ~/public_html.bak && ln -s ~/portfolio/public ~/public_html                       # cPanel, primary domain
   mv ~/domains/example.com/public_html{,.bak} && ln -s ~/portfolio/public ~/domains/example.com/public_html   # DirectAdmin
   ```

If AI extraction is on and you have no process manager, add a cron job instead: `* * * * * cd ~/portfolio && php artisan queue:work --stop-when-empty`.
