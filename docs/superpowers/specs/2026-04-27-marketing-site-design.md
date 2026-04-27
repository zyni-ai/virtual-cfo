# Virtual CFO — Marketing Website

> **How to use this file:** Copy this file as `CLAUDE.md` into the root of a new Next.js project. It contains everything needed to build the marketing website from scratch — product context, design decisions, page copy, component breakdown, and build instructions.

---

## What is Virtual CFO?

Virtual CFO is a financial automation application built by Zysk Technologies. It eliminates manual Tally data entry for accounts teams and business owners.

**The problem it solves:** Accounts teams waste hours every month manually entering bank and credit card transactions into Tally — downloading statements, formatting data, identifying account heads, reconciling invoices, and typing it all in. Virtual CFO automates the entire pipeline.

**The pipeline:** Upload → Parse → Map & Reconcile → Export → Track

- Upload bank statements, credit card statements, and invoices (any format)
- AI extracts every transaction automatically
- AI maps transactions to account heads and reconciles against invoices
- Export Tally-compatible XML, CSV, or Excel in one click
- Track spending trends, budgets, and reconciliation status in built-in reports

**Key differentiators:**
- Works with any bank or credit card — no per-bank templates or setup
- Password-protected PDF support
- Zoho Invoice integration + email inbox for automatic document delivery
- Duplicate detection before processing
- GST-ready Tally export (CGST, SGST, IGST, TDS all split correctly)
- Configurable Tally ledger names per company
- Team access with roles (Admin, Accountant, Viewer)
- Budget tracking per account head
- Full audit trail with sensitive field masking
- Multi-company support — one login, multiple companies

---

## This Website's Purpose

This is a **pre-pitch showcase site** — not a conversion funnel. When a founder or accounts team lead is about to see a demo, this link is sent to them first. The goal is to explain what the product does, show what it looks like, and answer "what does this actually do?" before the call.

There is **no sign-up flow**, no pricing page, no waitlist. One page. One contact email at the bottom.

---

## Target Audience

**Primary:**
- **Accounts teams within companies** — people who upload statements monthly, manually enter into Tally, and chase invoices. They care about time saved, accuracy, and not having to learn a new complex tool.
- **Founders and business owners** — they want confidence that accounts are handled correctly without micromanaging. They care about budget visibility, Tally accuracy, and not needing to hire more people.

**Tone for this audience:** Direct, factual, finance-professional language. No buzzwords like "revolutionary", "next-gen", or "game-changing". No jargon like "reconciliation engine" or "AI-powered NLP pipeline". Speak like someone who has done accounts work and knows what the pain actually feels like.

---

## Reference Websites

Study these before building. Each is referenced for a specific reason — don't just copy the aesthetic, understand *why* it works.

### Primary references (closest to what we're building)
| Site | What to borrow |
|---|---|
| `dext.com` | Direct category peer — accounting automation for finance teams. Study how they explain a complex workflow simply, their section structure, and how they speak to non-technical finance people |
| `stripe.com` | Gold standard for breaking down a technically complex product into clean, digestible sections. Study their hero layout, feature section spacing, and how they use screenshots |

### Aesthetic references
| Site | What to borrow |
|---|---|
| `linear.app` | Typography scale, whitespace rhythm, and how to make a B2B product feel premium without being flashy |
| `loom.com` | "Here's the problem → here's the workflow → here's the outcome" page structure. Very close to our pipeline-led approach |
| `cal.com` | Simple, honest SaaS positioning. Clean light design with strong hierarchy |

### Avoid copying directly
| Site | Why not |
|---|---|
| `raycast.com` | Dark theme — we're light |
| `vercel.com` | Too developer-focused in tone — our audience is finance, not engineering |

---

## Design Direction

**Style:** Light + professional. Inspired by Stripe and Dext. Clean white backgrounds, strong typographic hierarchy, subtle borders, real product screenshots.

**What to avoid:**
- Dark mode / dark backgrounds
- Heavy gradients or glowing effects
- Illustration-heavy sections (use real screenshots only)
- Dense feature grids with tiny text
- Generic SaaS hero stock photos

**Visual rhythm:** Generous whitespace. Each section breathes. Text never competes with screenshots.

### Color Palette

| Token | Hex | Usage |
|---|---|---|
| `background` | `#FFFFFF` | Page background |
| `surface` | `#F9FAFB` | Section alternating bg, cards |
| `border` | `#E5E7EB` | Dividers, card borders |
| `text-primary` | `#111827` | Headings, body |
| `text-secondary` | `#6B7280` | Subtext, descriptions |
| `accent` | `#2563EB` | CTA button, active states, highlights |
| `accent-hover` | `#1D4ED8` | Button hover |

### Typography

- **Font:** Geist Sans (Next.js default via `next/font/google`) — falls back to Inter
- **Heading scale:** 48px (hero), 36px (section), 24px (subsection), 18px (card title)
- **Body:** 16px / line-height 1.6
- **Weight:** 700 headings, 400 body, 500 labels

---

## Tech Stack

```
Next.js 15         — App Router, React Server Components
Tailwind CSS v4    — Utility-first styling
shadcn/ui          — Button, Card, Badge components only
Framer Motion      — Subtle scroll-triggered fade-ins only
next/font          — Geist Sans
next/image         — Optimised screenshots
```

**Do not add:** CMS, database, auth, API routes, form handling libraries. This is a static marketing site. No backend needed.

---

## Project Structure

```
/
├── app/
│   ├── layout.tsx          # Root layout, font, metadata
│   ├── page.tsx            # Single page — imports all sections
│   └── globals.css         # Tailwind base + custom tokens
├── components/
│   ├── sections/
│   │   ├── Hero.tsx
│   │   ├── Pipeline.tsx    # 5-step pipeline walkthrough
│   │   ├── Features.tsx    # 6-card feature grid
│   │   ├── WhoItsFor.tsx
│   │   └── Footer.tsx
│   └── ui/                 # shadcn/ui components live here
├── public/
│   └── screenshots/        # All product screenshots go here
│       ├── dashboard.png
│       ├── upload.png
│       ├── transactions.png
│       ├── mapping.png
│       ├── export.png
│       └── reports.png
└── CLAUDE.md               # This file
```

---

## Page Sections — Full Spec

### Navbar

Minimal fixed navbar. Left: "Virtual CFO" wordmark in `text-primary` semibold. Right: single "Get in touch" anchor link scrolling to footer. White background, subtle bottom border `border`. No hamburger menu needed — single page.

---

### Section 1 — Hero

**Layout:** Centered text block, max-width 720px, full-width screenshot card below.

**Headline (48px, 700):**
> Your accounts team shouldn't be typing into Tally.

**Subheadline (20px, 400, `text-secondary`):**
> Virtual CFO imports your bank statements, credit card statements, and invoices — matches them against each other, flags discrepancies, and exports Tally-ready XML automatically. AI does the heavy lifting. Your team just reviews.

**CTA:** Single "Get in touch →" button (`accent` background, white text, rounded-lg). Anchors to `#contact` section.

**Visual:** Large screenshot of the dashboard / transaction list in a rounded card with subtle shadow. `next/image`, priority load. Place at `/public/screenshots/dashboard.png`.

**Section background:** White.

---

### Section 2 — Pipeline (5 Steps)

**Layout:** Each step alternates — screenshot left + text right, then text left + screenshot right. On mobile, stacks vertically with text above screenshot.

**Section heading (36px, 700):**
> How it works

**Section subheading (`text-secondary`):**
> From upload to Tally in five steps.

**Section background:** Alternating white / `surface` per step.

---

**Step 1 — Upload**

Badge: `Step 1`
Title: Upload your documents
Body:
> Bank statements, credit card statements, invoices. PDF, CSV, or XLSX — including password-protected files. Upload directly, connect Zoho for automatic invoice delivery, or forward documents to your Virtual CFO inbox. Duplicate files are detected automatically before processing begins.

Screenshot: `/public/screenshots/upload.png` — the imported files list / upload screen.

---

**Step 2 — Parse**

Badge: `Step 2`
Title: AI extracts every transaction
Body:
> No manual data entry. AI reads every document — any bank, any format, any layout — and extracts every transaction. Date, description, debit, credit, balance. Done in seconds.

Screenshot: `/public/screenshots/transactions.png` — transaction list populated after parsing.

---

**Step 3 — Map & Reconcile**

Badge: `Step 3`
Title: Transactions mapped. Invoices matched.
Body:
> AI maps every transaction to the right account head. Bank and credit card entries are cross-referenced against invoices — discrepancies like missing invoices, amount mismatches, and unpaid bills are flagged for review. Your team reviews only what needs attention, based on their role.

Screenshot: `/public/screenshots/mapping.png` — transaction mapping screen with account heads and confidence scores.

---

**Step 4 — Export**

Badge: `Step 4`
Title: Export to Tally, CSV, or Excel
Body:
> Generate Tally-compatible XML with full GST breakup — CGST, SGST, TDS split correctly — along with a reconciliation match report showing which transactions were matched against invoices and what needs attention. Or export to CSV and Excel: full transaction list, account holder name, opening and closing balances, and a clean summary. Everything in one click.

Screenshot: `/public/screenshots/export.png` — export options screen.

---

**Step 5 — Track**

Badge: `Step 5`
Title: Stay on top of spending
Body:
> Once exported, the work doesn't stop. Monitor spending against budgets by account head. View trends, expense breakdowns, and monthly debit/credit comparisons — all filterable by date, bank, credit card, and financial year. Your accounts team gets a live picture of where money is going, not just what was entered into Tally.

Screenshot: `/public/screenshots/reports.png` — reports page with charts.

---

### Section 3 — Features

**Layout:** 2-column card grid. Each card: icon (Lucide), title (18px 600), body (15px `text-secondary`). Cards have a border, `surface` background, rounded-xl, generous padding.

**Section heading:**
> Everything your accounts team needs

**Section background:** White.

---

**Card 1 — Works with any bank or credit card**
> Any format, any layout. No templates or per-bank setup. PDFs, CSVs, Excel, even password-protected statements.

Icon: `CreditCard`

---

**Card 2 — GST-ready Tally export**
> CGST, SGST, IGST, TDS — all split correctly. Ledger names configurable per your Tally setup. Import directly, no reformatting.

Icon: `FileCheck`

---

**Card 3 — Reports that actually tell you something**
> Spending trends, top account heads, expense breakdown by bank or card, monthly debit/credit comparison. All filterable by date, account, and financial year.

Icon: `BarChart2`

---

**Card 4 — Team access with roles**
> Add your accountant, CA, or finance team. Admin, Accountant, and Viewer roles. Everyone sees what they need to, nothing more.

Icon: `Users`

---

**Card 5 — Budgets & reconciliation**
> Set budgets per account head. Match bank and credit card entries against invoices. Mismatches, unpaid bills, and overspend — all flagged automatically.

Icon: `Scale`

---

**Card 6 — AI that improves with use**
> Every mapping you confirm trains the system. Create rules from any transaction. Review queue for low-confidence suggestions. The more you use it, the less you touch it.

Icon: `Sparkles`

---

### Section 4 — Who it's for

**Layout:** Two columns side by side. Each column: bold label, paragraph below. On mobile, stacks.

**Section heading:**
> Built for the people who do the work

**Section background:** `surface`

---

**Column 1 — Accounts Teams**
> You're uploading statements every month, manually entering transactions into Tally, and chasing invoices for reconciliation. Virtual CFO handles the entry, the matching, and the flagging — so your team focuses on review, not data entry.

---

**Column 2 — Founders & Business Owners**
> You don't want to micromanage your accounts. You want to know money is going into Tally correctly, budgets are being tracked, and nothing is slipping through. Virtual CFO gives you that confidence without adding headcount.

---

### Section 5 — Footer / Contact

**id:** `contact`

**Layout:** Centered, minimal. Three rows:

1. "Virtual CFO by Zysk Technologies"
2. Contact email: `[TODO: add contact email]` (link with `mailto:`)
3. Small text links: Privacy Policy · Terms of Service (placeholder `#` hrefs for now)

**Background:** `#111827` (dark). Text: white / `text-gray-400` for secondary. This is the only dark element on the page — gives the page a clean visual closure.

---

## Animations

Keep it subtle. Only use Framer Motion for:
- **Fade-up on scroll** — each pipeline step fades in as it enters the viewport (`opacity: 0 → 1`, `y: 20 → 0`, duration 0.4s)
- **No parallax, no stagger cascades, no hover 3D transforms**

Use `motion.div` with `whileInView` and `viewport={{ once: true }}` so animations only trigger once.

---

## Screenshots

Screenshots must be **real** — taken from the actual Virtual CFO Filament admin panel. They should be:
- 1280px wide browser window (no browser chrome visible, just the app)
- Cropped to show only the relevant section
- Saved as `.png` at 2x resolution (retina)
- Named exactly as referenced in the section specs above

Place all screenshots in `/public/screenshots/`. Use `next/image` with `width`, `height`, and `alt` props. Never use `<img>` tags.

**If screenshots are not yet available:** Use a placeholder (gray rectangle with the label) and add a `TODO:` comment so it's easy to swap in later.

---

## Metadata

```tsx
// app/layout.tsx
export const metadata = {
  title: 'Virtual CFO — Automated Tally Entry for Accounts Teams',
  description:
    'Import bank statements, credit card statements, and invoices. AI maps transactions, reconciles invoices, and exports Tally-ready XML automatically.',
  openGraph: {
    title: 'Virtual CFO',
    description: 'Stop doing Tally entries manually.',
    // Add og:image once screenshot is ready
  },
}
```

---

## Build Instructions

```bash
npx create-next-app@latest virtual-cfo-marketing --typescript --tailwind --eslint --app --src-dir no --import-alias "@/*"
cd virtual-cfo-marketing

# Install dependencies
npm install framer-motion lucide-react
npx shadcn@latest init
npx shadcn@latest add button card badge
```

**shadcn init settings:** Style `default`, base color `zinc`, CSS variables `yes`.

---

## What NOT to build

- No routing beyond the single `/` page
- No API routes
- No authentication
- No forms (the contact CTA is just a `mailto:` link)
- No CMS or MDX
- No analytics (add later once the domain is live)
- No i18n
- No dark mode toggle

Keep it simple. The goal is a fast, readable, single-page site that loads in under 1 second.

---

## Done Criteria

The site is complete when:
- [ ] All 5 sections render correctly on desktop (1280px)
- [ ] All 5 sections render correctly on mobile (375px)
- [ ] All 6 screenshots render with `next/image` (or placeholders clearly marked)
- [ ] "Get in touch" button scrolls to footer
- [ ] Footer contact email opens mail client
- [ ] `npm run build` completes with zero errors
- [ ] No TypeScript errors
- [ ] Lighthouse performance score ≥ 90
