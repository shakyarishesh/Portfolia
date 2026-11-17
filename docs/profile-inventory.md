# Profile inventory

This is what the initial seed (`database/seeders/PortfolioSeeder.php`) is based on, where each fact came from, and what still needs your review.

## Sources actually inspected

| Source | What it contained |
|---|---|
| `rishesh_shakya.pdf` (your CV) | The primary source: contact details, objective, experience, projects, skills, education and hyperlinks. |
| `static-site/` (the previous portfolio) | The Hajir engineering highlights and the timeline. |
| Git history of `hajir-v2` and `hajir_dashboard` | Commit counts, first and last commit dates, and the frontend stack. |
| Claude Code memory notes, `~/.claude/projects/-home-rishesh-projects-veloxlabs*/memory/*.md` (35 notes) | Technical work on Hajir. |
| Claude Code transcripts, `~/.claude/projects/*/*.jsonl` (about 100 sessions, Jun–Oct 2026) | Your own messages only. These were mined for career facts. Secrets, customer data and other people's data were ignored. |

**Not available:** claude.ai web or desktop conversation history. It is not on this machine. Use the import screen with a claude.ai data export to bring it in.

## Verified facts (published)

| Fact | Evidence |
|---|---|
| Rishesh Shakya, Lalitpur, Nepal; shakyarises1211@gmail.com; +977-9841701082 | CV |
| GitHub `shakyarishesh`, LinkedIn `risheshakya7` | CV hyperlinks |
| Software Developer, Velox Labs, Kathmandu, Oct 2024 – present | CV |
| Backend Developer Intern, Times Global, Jun – Oct 2024 | CV |
| Hajir: HR/payroll SaaS, Oct 2024 – present, web.hajirhr.com, and its 8 feature bullets | CV, plus memory notes and git |
| Dialinger: cloud telephony, Oct 2024 – Apr 2025, app.dialinger.com (Twilio, Stripe, KYC, TwiML) | CV |
| School Management System, Sept 2025 – present, school-accounting.vercel.app | CV |
| BIT, Patan Multiple Campus, 2019 – 2024 | CV |
| Skills: PHP, JS, SQL, Laravel, REST, WebSockets, auth, DB design, PostgreSQL, MySQL, Stripe, Twilio, payment gateways, SMS/telephony APIs, Git, Linux, Postman | CV |
| Next.js 14, React 18, MUI 5, Redux Toolkit; PHPUnit; Docker; Sanctum/Passport; queues | Git and transcripts (not in the CV) |
| 5,600+ backend commits (3,675 + 2,000 across two author identities) and 500+ frontend commits (550) | `git shortlog` |
| Hajir engineering highlights: payroll engine, tax engine, class pay, advances, gratuity, statutory reports, calendar switch, supervisor scope | Memory notes and the previous site |

## Uncertain: seeded as `needs_review` or noted for you

- **"Earned > monthly salary" bug-hunt card.** It quotes an internal production figure (556 candidate-months). It is hidden until you decide whether to publish it.
- **Email.** A GitHub README in the transcripts uses `shakyarishesh@gmail.com`, while the CV uses `shakyarises1211@gmail.com`. The seed uses the CV address.
- **Velox Labs title.** An older CV draft says "Backend Developer"; the final CV says "Software Developer", and the seed uses the final one.
- **Start date.** Your first `hajir-v2` commit is dated 2024-12-12, but the CV says Oct 2024. The CV date is kept, since earlier work may predate the repository.
- **Shift management.** Git history shows another developer built it, and you extended and maintained it. The wording says "maintain/extend", not "developed".
- **School Management System.** There is no evidence of its tech stack or employer/client, so no technologies are attached.
- **Skill levels.** "Strong" is set only where the CV claims strong experience (Laravel, PHP, REST APIs, MySQL, relational database design). All other skills are unrated, and no meter is shown for them. Rate them yourself in the admin.

## Missing

- Certifications: none found in any source, so the section shows only education.
- Project screenshots and thumbnails.
- GitHub repositories worth featuring. None of your public repos appear in any source, and the Hajir repos are company-private.

## Deliberately excluded

- **web-to-figma-main:** a third-party tool you set up, not your project.
- **"Topper of 8th Semester, Kantipur Engineering College" and "Team Lead, Veloxlabs (Sep 2024)":** these are commented-out leftovers in your LaTeX CV template that belong to someone else's CV.
- **Company-internal URLs** (staging servers, private repos), customer data and salary figures.
