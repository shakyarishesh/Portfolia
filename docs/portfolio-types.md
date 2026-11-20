# Portfolio types

Every portfolio has a **type**. The type decides which project fields the forms show, what the sections and
links are called, which sections a new portfolio starts with, and which templates are offered.
The seven types are Technical & IT, Creative & Design, Business & Marketing, Management & Leadership,
Education & Training, General Professional, and Hybrid. A Hybrid portfolio combines two or more of the others.

```bash
php artisan migrate   # adds portfolio_types (seeded with the 7 types), portfolios.type/disciplines,
                      # projects.details and sections.body. Additive only, and it rolls back cleanly.
```

Existing portfolios become **Technical & IT**. Their content, URLs and templates do not change.

## How it fits together

| Piece | Where | Editable by |
|---|---|---|
| Types: name, icon, description, categories, modules, starting sections, compatible templates, active | `portfolio_types` table, seeded from `config/portfolio.php` → `types` | Admin → Portfolio types |
| Modules: the project fields and wording of a profession | `config/portfolio.php` → `modules` | Code. Admins choose which modules each type uses |
| Profession-specific project fields (client, strategy, metrics, budget …) | `projects.details` JSON. Field definitions are in `config/portfolio.php` → `project_details` | Project form |
| Free-text sections (Teaching philosophy, Publications, custom …) | `sections.body` (Markdown). Catalogue is in `config/portfolio.php` → `extra_sections` | Admin → Sections & order |
| Template ↔ type compatibility | `portfolio_types.templates` | Admin → Portfolio types → matrix |

## Behaviour

- **`/create` starts with the type.** The applicant picks a type card (for Hybrid, ticks two or more
  disciplines). They then fill in their details, choose a template from those compatible with the type,
  and describe their work. The work fields depend on the type.
  - **Preview** renders what they entered with the session-only demo. Nothing is saved.
  - **Submit** sends the portfolio for admin approval.
  - **"See a sample"** shows example content for each type.
- **Changing type.** Owners and admins change the type under Template & settings → Portfolio type.
  - Nothing is deleted or hidden.
  - The new type's sections are added, empty and not public until written.
  - Optionally, the Projects and Skills tabs take the new type's words.
  - Fields that belong only to the old type stay stored and come back if the type is switched back.
  - Those fields are not shown publicly while the type doesn't use them.
- **Public pages.** Each template shows only filled fields of the portfolio's own modules, and empty
  sections are never rendered.
  - The wording follows the type ("Teaching", "View online").
  - Metrics written as `Label: value` become figure tiles. A percentage also gets a bar.
  - YouTube and Vimeo links are embedded through privacy-friendly players.
  - Supporting PDFs are listed as downloads.
  - The budget field is never shown.
- **Templates.** Only compatible templates are offered and accepted. A portfolio's current template
  always stays available to it.

## Adding a profession

1. **Same fields as an existing profession:** create the type in Admin → Portfolio types and pick its
   modules, sections and templates. Its "See a sample" demo shows the Technical sample content until you
   give it a `sample` in `config/portfolio.php` → `types`.
2. **New fields:**
   1. Add them to `project_details`.
   2. Add a module to `modules` that lists them in `project_fields`, with the type's words in `work` and `skills`.
   3. Tick the module on the type.
