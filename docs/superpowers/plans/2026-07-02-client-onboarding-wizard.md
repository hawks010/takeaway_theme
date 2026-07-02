# Client Onboarding Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Portable handoff note:** This plan is written to be executed standalone by any coding agent (including Codex) with zero access to prior conversation history. Every decision, file path, and code shape needed is in this document or the linked spec. Read the spec first: [`docs/superpowers/specs/2026-07-02-client-onboarding-wizard-design.md`](../specs/2026-07-02-client-onboarding-wizard-design.md).

**Goal:** Replace the plain-text public magic-link form in `TTOS_Client_Intake` with a 17-step React wizard (menu builder, payment/email/accounting/newsletter/analytics self-connect, VAT/legal auto-draft, Palette Studio branding), plus a persistent "Brand Guide" wp-admin screen — without changing any existing security guarantees.

**Architecture:** The existing PHP backend (`class-client-intake.php`) keeps its hashed-token validation, rate limiting, and upload handling. A new REST layer (`class-intake-rest.php`) exposes step-by-step save/load endpoints for a new React SPA (`intake/`, Vite + React 18, IIFE build — same pattern as the existing `wizard/` and `dashboard/` apps in this plugin). A new shared OAuth helper (`class-oauth-connectors.php`) handles Stripe/Google/Microsoft/Mailchimp connects using the same encrypted-token-storage pattern `TTOS_Accounting` already uses for Xero/QBO/FreeAgent.

**Tech Stack:** PHP 8.2 (WordPress/WooCommerce 10.x), React 18 + Vite 5 (matching `wizard/` and `dashboard/`), `@dnd-kit/core` + `@dnd-kit/sortable` for accessible drag-and-drop (new dependency — first use in this plugin, chosen specifically for built-in keyboard sensor support per the spec's §7.1 requirement).

---

## Stage map (execute in order — each stage produces working, testable software before the next begins)

| Stage | What it delivers | Depends on |
|---|---|---|
| 0 | Prerequisites checklist (credentials, accounts) | — |
| 1 | React wizard shell + shared UI components | 0 |
| 2 | Backend REST layer for the wizard | 0 |
| 3 | Branding steps (Palette, Layout, Typography, Logo/Photos, Summary) | 1, 2 |
| 4 | Simple grouped-field steps (Business, Hours, Delivery) | 1, 2 |
| 5 | Menu builder UI (accessible drag-and-drop) | 1, 2 |
| 6 | Menu import → real WooCommerce products | 5 |
| 7 | Shared OAuth helper + Payment connect | 2 |
| 8 | Email, Accounting, Newsletter, Analytics connect steps | 7 |
| 9 | VAT & legal step + auto-drafted policy pages | 2, 4 |
| 10 | Brand Guide wp-admin screen | 3, 6 |
| 11 | Full wizard assembly, end-to-end pass, final build report | all above |

---

## Stage 0: Prerequisites checklist

**No code in this stage.** These are accounts/credentials that must exist before Stage 7–8 can be tested end-to-end (Stages 1–6 and 9–10 do not need them and can proceed in parallel).

- [ ] **Step 1: Confirm developer accounts exist**

The following must be registered under Inkfire's own developer accounts before Stage 7/8 integration testing:

| Provider | What's needed | Used in |
|---|---|---|
| Stripe | Stripe Connect platform application (client ID + secret) | Stage 7 |
| Google Cloud | OAuth 2.0 app with `gmail.send` scope, consent screen verified | Stage 8 |
| Microsoft Azure | App registration with `Mail.Send` delegated permission | Stage 8 |
| Mailchimp | OAuth app (or API-key-only integration — decide in Stage 8) | Stage 8 |
| Xero / QuickBooks / FreeAgent | **Already done** — `TTOS_Accounting` has working credentials today | Stage 8 |
| Google Analytics | **Nothing needed** — client pastes their own Measurement ID | Stage 8 |

- [ ] **Step 2: Store new credentials**

Add to the server's `wp-config.php` (never in the plugin code or git):

```php
define('TTOS_STRIPE_CONNECT_CLIENT_ID', '...');
define('TTOS_STRIPE_CONNECT_SECRET', '...');
define('TTOS_GOOGLE_OAUTH_CLIENT_ID', '...');
define('TTOS_GOOGLE_OAUTH_SECRET', '...');
define('TTOS_MICROSOFT_OAUTH_CLIENT_ID', '...');
define('TTOS_MICROSOFT_OAUTH_SECRET', '...');
define('TTOS_MAILCHIMP_CLIENT_ID', '...');
define('TTOS_MAILCHIMP_SECRET', '...');
```

If a credential is missing, its connector step must still render (never fatal) and show "Connect [Provider]" as disabled with the tooltip "Not yet available — contact your developer." This is implemented in Stage 7/8 — noting it here so Stage 0 blockers don't silently break other stages.

- [ ] **Step 3: Confirm PHP/Node versions on target server match dev**

```bash
ssh hostinger-shared "php -v && node -v && npm -v"
```
Expected: PHP 8.2.x, Node v24.14.0, npm v11.9.0 (matching existing `wizard/`/`dashboard/` builds).

---

## Stage 1: React wizard shell + shared UI components

**Files:**
- Create: `takeaway-os/intake/package.json`
- Create: `takeaway-os/intake/vite.config.js`
- Create: `takeaway-os/intake/src/index.jsx`
- Create: `takeaway-os/intake/src/IntakeWizard.jsx`
- Create: `takeaway-os/intake/src/api.js`
- Create: `takeaway-os/intake/src/steps.js`
- Create: `takeaway-os/intake/src/components/ProgressBar.jsx`
- Create: `takeaway-os/intake/src/components/HelpTip.jsx`
- Create: `takeaway-os/intake/src/components/SkipButton.jsx`
- Create: `takeaway-os/intake/src/components/VisualCardPicker.jsx`
- Create: `takeaway-os/intake/src/steps/Welcome.jsx`
- Create: `takeaway-os/intake/src/steps/Finish.jsx`

- [ ] **Step 1: Scaffold the Vite/React project**

`takeaway-os/intake/package.json`:
```json
{
  "name": "ttos-intake",
  "private": true,
  "version": "1.0.0",
  "type": "module",
  "scripts": { "build": "vite build" },
  "dependencies": {
    "react": "^18.3.1",
    "react-dom": "^18.3.1",
    "@dnd-kit/core": "^6.1.0",
    "@dnd-kit/sortable": "^8.0.0",
    "@dnd-kit/utilities": "^3.2.2"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.3.4",
    "vite": "^5.4.0"
  }
}
```

`takeaway-os/intake/vite.config.js`:
```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      input: 'src/index.jsx',
      output: {
        entryFileNames: 'intake.js',
        assetFileNames: 'intake.css',
        format: 'iife',
        name: 'TTOSIntake'
      }
    },
    outDir: 'dist',
    emptyOutDir: true
  }
})
```

- [ ] **Step 2: Write `api.js` — the REST client**

This calls the endpoints Stage 2 will build. Written now so Stage 1 components have something to import; Stage 2 makes these calls succeed.

`takeaway-os/intake/src/api.js`:
```js
const cfg = () => window.ttosIntake || {}

async function request(path, method = 'GET', body = null) {
  const { apiBase, intakeId, token } = cfg()
  const url = apiBase + path
  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify({ ...body, intake_id: intakeId, token }) : JSON.stringify({ intake_id: intakeId, token })
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.message || ('HTTP ' + res.status))
  }
  return res.json()
}

export const api = {
  loadRecord: () => request('/intake/record', 'POST'),
  saveStep: (stepId, data) => request('/intake/save-step', 'POST', { step_id: stepId, data }),
  submitFinal: () => request('/intake/submit', 'POST'),
  startConnect: (provider) => request('/intake/connect/' + provider + '/start', 'POST'),
}
```

- [ ] **Step 3: Write the step registry**

`takeaway-os/intake/src/steps.js` — single source of truth for step order, matching spec §5's table exactly:
```js
export const STEPS = [
  { id: 'welcome',             required: true,  title: 'Welcome' },
  { id: 'business_basics',     required: true,  title: 'Business basics' },
  { id: 'palette',             required: true,  title: 'Colours' },
  { id: 'layout_style',        required: true,  title: 'Layout style' },
  { id: 'typography',          required: true,  title: 'Typography' },
  { id: 'logo_photos',         required: true,  title: 'Logo & photos' },
  { id: 'brand_summary',       required: true,  title: 'Brand summary' },
  { id: 'opening_hours',       required: true,  title: 'Opening hours' },
  { id: 'menu_builder',        required: true,  title: 'Menu builder' },
  { id: 'delivery_collection', required: true,  title: 'Delivery & collection' },
  { id: 'payments',            required: true,  title: 'Payments' },
  { id: 'order_email',         required: false, title: 'Order emails' },
  { id: 'accounting',          required: false, title: 'Accounting' },
  { id: 'newsletter',          required: false, title: 'Newsletter' },
  { id: 'analytics',           required: false, title: 'Google Analytics' },
  { id: 'vat_legal',           required: true,  title: 'VAT & legal' },
  { id: 'finish',              required: true,  title: 'Finish' },
]

export function stepIndex(id) {
  return STEPS.findIndex(s => s.id === id)
}
```

- [ ] **Step 4: Write the shared components**

`takeaway-os/intake/src/components/ProgressBar.jsx`:
```jsx
import React from 'react'

export default function ProgressBar({ current, total }) {
  const pct = total <= 1 ? 0 : Math.round((current / (total - 1)) * 100)
  return (
    <div role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}
      style={{ height: 6, background: 'var(--tt-border, #e5d6c5)', borderRadius: 3, overflow: 'hidden' }}>
      <div style={{ height: '100%', width: pct + '%', background: 'var(--tt-primary, #d83a16)', transition: 'width .3s' }} />
    </div>
  )
}
```

`takeaway-os/intake/src/components/HelpTip.jsx` — the `?` icon with inline plain-English tip (spec §5, "help icon on every step"):
```jsx
import React, { useState } from 'react'

export default function HelpTip({ text }) {
  const [open, setOpen] = useState(false)
  return (
    <span style={{ display: 'inline-block', marginLeft: 6, verticalAlign: 'middle' }}>
      <button
        type="button"
        aria-expanded={open}
        aria-label="Help for this question"
        onClick={() => setOpen(o => !o)}
        style={{
          width: 18, height: 18, borderRadius: '50%', border: '1px solid var(--tt-border, #e5d6c5)',
          background: 'var(--tt-surface, #fff)', color: 'var(--tt-muted, #75665c)', fontSize: 11,
          cursor: 'pointer', lineHeight: '16px'
        }}
      >?</button>
      {open && (
        <div role="note" style={{
          marginTop: 8, padding: '10px 12px', background: 'var(--tt-surface-soft, #f8efe4)',
          borderRadius: 8, fontSize: 12, color: 'var(--tt-text, #1f1712)', maxWidth: 420
        }}>{text}</div>
      )}
    </span>
  )
}
```

`takeaway-os/intake/src/components/SkipButton.jsx` — the Skip + optional note pattern (spec §8.2). Required steps must never render this component; instead they render a `mailto:support@inkfire.co.uk` link (built inline where needed, no separate component required since it's a single `<a>` tag).
```jsx
import React, { useState } from 'react'

export default function SkipButton({ onSkip }) {
  const [showNote, setShowNote] = useState(false)
  const [note, setNote] = useState('')

  if (!showNote) {
    return (
      <button type="button" onClick={() => setShowNote(true)}
        style={{ padding: '8px 14px', border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8, background: 'transparent', color: 'var(--tt-muted, #75665c)', fontSize: 13, cursor: 'pointer' }}>
        Skip this step
      </button>
    )
  }

  return (
    <div style={{ marginTop: 10 }}>
      <label style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)' }}>
        Optional note for your developer
        <input
          type="text"
          value={note}
          onChange={e => setNote(e.target.value)}
          placeholder="e.g. not sure what this means"
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }}
        />
      </label>
      <button type="button" onClick={() => onSkip(note)}
        style={{ marginTop: 8, padding: '8px 14px', border: 'none', borderRadius: 8, background: 'var(--tt-muted, #75665c)', color: '#fff', fontSize: 13, cursor: 'pointer' }}>
        Confirm skip
      </button>
    </div>
  )
}
```

`takeaway-os/intake/src/components/VisualCardPicker.jsx` — reused by layout style (Stage 3) and payment/newsletter/accounting connect grids (Stage 7/8):
```jsx
import React from 'react'

export default function VisualCardPicker({ options, value, onChange, columns = 3 }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${columns}, 1fr)`, gap: 10 }}>
      {options.map(opt => {
        const selected = value === opt.value
        const dimmed = value && !selected
        return (
          <button
            key={opt.value}
            type="button"
            onClick={() => onChange(opt.value)}
            aria-pressed={selected}
            style={{
              padding: 12, borderRadius: 10, textAlign: 'center', cursor: 'pointer',
              border: selected ? '1px solid var(--tt-primary, #d83a16)' : '1px solid var(--tt-border, #e5d6c5)',
              background: selected ? 'var(--tt-surface-soft, #f8efe4)' : 'var(--tt-surface, #fff)',
              opacity: dimmed ? 0.45 : 1
            }}
          >
            {opt.icon && <div style={{ fontSize: 22, marginBottom: 6 }}>{opt.icon}</div>}
            <div style={{ fontSize: 13, color: 'var(--tt-text, #1f1712)' }}>{opt.label}</div>
            {opt.sub && <div style={{ fontSize: 11, color: 'var(--tt-muted, #75665c)' }}>{opt.sub}</div>}
          </button>
        )
      })}
    </div>
  )
}
```

- [ ] **Step 5: Write the wizard shell**

`takeaway-os/intake/src/IntakeWizard.jsx` — the stepper engine every step plugs into. Handles: current step, autosave-on-next, Back/Next/Skip navigation, loading/error states.

```jsx
import React, { useState, useEffect, useCallback } from 'react'
import { STEPS } from './steps.js'
import { api } from './api.js'
import ProgressBar from './components/ProgressBar.jsx'
import Welcome from './steps/Welcome.jsx'
import Finish from './steps/Finish.jsx'
import SkipButton from './components/SkipButton.jsx'

const STEP_COMPONENTS = { welcome: Welcome, finish: Finish }
// Stages 3-9 register their step components into STEP_COMPONENTS via registerStep() (Step 6 below).

export function registerStep(id, Component) {
  STEP_COMPONENTS[id] = Component
}

export default function IntakeWizard() {
  const [idx, setIdx] = useState(0)
  const [record, setRecord] = useState(null)
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    api.loadRecord()
      .then(r => {
        setRecord(r)
        const resumeIdx = STEPS.findIndex(s => !r.submitted_data?.[s.id]?.__complete)
        setIdx(resumeIdx >= 0 ? resumeIdx : 0)
      })
      .catch(e => setError(e.message))
  }, [])

  const step = STEPS[idx]
  const Component = step ? STEP_COMPONENTS[step.id] : null

  const goNext = useCallback(async (stepData) => {
    setSaving(true)
    try {
      const updated = await api.saveStep(step.id, { ...stepData, __complete: true })
      setRecord(updated)
      if (idx < STEPS.length - 1) setIdx(idx + 1)
    } catch (e) {
      setError(e.message)
    } finally {
      setSaving(false)
    }
  }, [idx, step])

  const goBack = useCallback(() => {
    if (idx > 0) setIdx(idx - 1)
  }, [idx])

  const handleSkip = useCallback(async (note) => {
    await goNext({ __skipped: true, __skip_note: note || '' })
  }, [goNext])

  if (error) {
    return <div role="alert" style={{ padding: 20 }}>Something went wrong: {error}. Please refresh and try again, or email <a href="mailto:support@inkfire.co.uk">support@inkfire.co.uk</a>.</div>
  }
  if (!record || !Component) {
    return <div style={{ padding: 20 }}>Loading…</div>
  }

  return (
    <div style={{ maxWidth: 640, margin: '0 auto', padding: 20 }}>
      <div style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)', marginBottom: 8 }}>
        Step {idx + 1} of {STEPS.length}
      </div>
      <ProgressBar current={idx} total={STEPS.length} />
      <div style={{ marginTop: 20 }}>
        <Component
          data={record.submitted_data?.[step.id] || {}}
          onNext={goNext}
          saving={saving}
        />
        {!step.required && step.id !== 'finish' && (
          <SkipButton onSkip={handleSkip} />
        )}
      </div>
      {idx > 0 && step.id !== 'finish' && (
        <button type="button" onClick={goBack} style={{ marginTop: 16, background: 'none', border: 'none', color: 'var(--tt-muted, #75665c)', cursor: 'pointer' }}>
          ← Back
        </button>
      )}
    </div>
  )
}
```

- [ ] **Step 6: Write Welcome and Finish steps**

`takeaway-os/intake/src/steps/Welcome.jsx` (spec §8.1):
```jsx
import React from 'react'

export default function Welcome({ onNext }) {
  return (
    <div style={{ textAlign: 'center' }}>
      <h1 style={{ fontSize: 22, marginBottom: 10 }}>Thanks for choosing Inkfire</h1>
      <p style={{ fontSize: 14, color: 'var(--tt-muted, #75665c)', maxWidth: 420, margin: '0 auto' }}>
        We're building your new takeaway website. This will take about 20 minutes —
        answer at your own pace, everything saves as you go, and you can stop and
        come back anytime using the same link.
      </p>
      <button type="button" onClick={() => onNext({})}
        style={{ marginTop: 20, padding: '11px 24px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        Get started
      </button>
    </div>
  )
}
```

`takeaway-os/intake/src/steps/Finish.jsx` (spec §8.3) — reads the whole record to build the recap, so it takes `record` not just `data`:
```jsx
import React from 'react'

function summarize(submitted) {
  const lines = []
  if (submitted.palette) lines.push('Colours & fonts set')
  if (submitted.menu_builder?.categories?.length) {
    const count = submitted.menu_builder.categories.reduce((n, c) => n + (c.sub_categories || []).reduce((m, s) => m + (s.items || []).length, 0), 0)
    lines.push(count + ' menu items added')
  }
  if (submitted.delivery_collection) lines.push('Delivery & collection configured')
  if (submitted.payments?.provider) lines.push(submitted.payments.provider + ' connected')
  const skipped = Object.keys(submitted).filter(k => submitted[k]?.__skipped)
  if (skipped.length) lines.push(skipped.length + ' step(s) skipped for your developer to finish')
  return lines
}

export default function Finish({ record }) {
  const lines = summarize(record?.submitted_data || {})
  return (
    <div style={{ textAlign: 'center' }}>
      <h1 style={{ fontSize: 22, marginBottom: 10 }}>All done — thank you!</h1>
      <p style={{ fontSize: 14, color: 'var(--tt-muted, #75665c)' }}>Here's what you told us:</p>
      <ul style={{ listStyle: 'none', padding: 0, fontSize: 14, color: 'var(--tt-text, #1f1712)' }}>
        {lines.map((l, i) => <li key={i} style={{ marginBottom: 6 }}>{l}</li>)}
      </ul>
      <p style={{ fontSize: 13, color: 'var(--tt-muted, #75665c)', marginTop: 16 }}>
        Questions? <a href="mailto:support@inkfire.co.uk" style={{ color: 'var(--tt-primary, #d83a16)' }}>support@inkfire.co.uk</a>
      </p>
    </div>
  )
}
```

`Finish` needs `record`, not `data` — update `IntakeWizard.jsx`'s render to pass `record={record}` as an additional prop alongside `data`/`onNext`/`saving` (all step components receive all three; only `Finish` uses `record`).

- [ ] **Step 7: Write `index.jsx`**

```jsx
import React from 'react'
import { createRoot } from 'react-dom/client'
import IntakeWizard from './IntakeWizard.jsx'

const el = document.getElementById('ttos-intake-root')
if (el) {
  createRoot(el).render(<IntakeWizard />)
}
```

- [ ] **Step 8: Build and verify**

```bash
cd takeaway-os/intake && npm install && npm run build
```
Expected: `dist/intake.js` and `dist/intake.css` created, no build errors.

- [ ] **Step 9: Stage 1 self-review loop**

Run this checklist. If anything fails, fix and re-run before moving to Stage 2:
1. `npm run build` completes with zero errors or warnings.
2. Open `dist/intake.js` and confirm it's a single IIFE (no `import`/`export` statements — those would mean an ES module leaked through, which breaks in wp-admin's non-module script context).
3. Every component file uses only CSS variables (`var(--tt-*, fallback)`) for colour — no hardcoded hex outside a fallback value, matching the CLAUDE.md standing rule.
4. `git log --oneline -1` shows a commit for this stage.

- [ ] **Step 10: Commit**

```bash
git add takeaway-os/intake/
git commit -m "feat: scaffold React intake wizard shell with shared components"
```

---

## Stage 2: Backend REST layer for the wizard

**Files:**
- Create: `takeaway-os/includes/class-intake-rest.php`
- Modify: `takeaway-os/includes/class-client-intake.php:733` (loosen `validated_public_record` visibility)
- Modify: `takeaway-os/includes/class-client-intake.php:942-955` (`maybe_render_public_form` — mount React instead of the PHP form)
- Modify: `takeaway-os/takeaway-os.php` (register new file + hook)

- [ ] **Step 1: Loosen method visibility for reuse**

In `takeaway-os/includes/class-client-intake.php:733`, change:
```php
private static function validated_public_record(string $id, string $token, bool $mark_open = false) {
```
to:
```php
public static function validated_public_record(string $id, string $token, bool $mark_open = false) {
```

This is the only visibility change needed — the new REST class calls this one method to validate every request; it does not need direct access to `sanitize_public_submission()` or `import_submission()` (those stay private and are called internally by existing methods that the REST class invokes indirectly, per Step 3 below).

- [ ] **Step 2: Add a public save-step entry point to `TTOS_Client_Intake`**

Add this new public method to `class-client-intake.php`, near `handle_public_posts()`:
```php
    /**
     * Save one step's data into a record's submitted_data, without requiring
     * final-submission validation. Used by the REST layer for autosave.
     */
    public static function save_step_data(array $record, string $step_id, array $step_data): array {
        $step_data = self::sanitize_step_payload($step_id, $step_data);
        if (!isset($record['submitted_data']) || !is_array($record['submitted_data'])) {
            $record['submitted_data'] = array();
        }
        $record['submitted_data'][$step_id] = $step_data;
        $status = self::effective_status($record);
        if (in_array($status, array('draft', 'sent'), true)) {
            $record['status'] = 'opened';
        } elseif ($status !== 'submitted' && $status !== 'imported') {
            $record['status'] = 'partially_completed';
        }
        self::save_record($record);
        return $record;
    }

    /**
     * Per-step sanitization. Three step ids safely fall through to
     * sanitize_public_submission()'s existing per-section shape because
     * the new wizard's field names for them match exactly:
     * business_basics, opening_hours, delivery_collection.
     *
     * Every other step id MUST have its own sanitizer registered via the
     * `ttos_intake_step_sanitizers` filter, added in the stage that
     * introduces it — palette/layout_style/typography (Stage 3),
     * menu_builder (Stage 5), payments (Stage 7 — note: this step's data
     * shape is unrelated to the legacy `ordering_payment` section, so
     * without its own sanitizer the fallback below would silently return
     * an empty array), order_email/accounting/newsletter/analytics
     * (Stage 8), vat_legal (Stage 9). Steps with no submitted fields at
     * all (welcome, brand_summary, finish, logo_photos — file uploads go
     * through a separate endpoint) safely sanitize to an empty array via
     * the fallback and need no dedicated entry.
     */
    private static function sanitize_step_payload(string $step_id, array $raw): array {
        $sanitizers = apply_filters('ttos_intake_step_sanitizers', array());
        if (isset($sanitizers[$step_id])) {
            return call_user_func($sanitizers[$step_id], $raw);
        }
        // Fallback: reuse the existing whole-form sanitizer's matching section
        // for step ids that already exist in sanitize_public_submission()'s output shape.
        $existing = self::sanitize_public_submission(array($step_id => $raw));
        return $existing[$step_id] ?? array();
    }
```

This uses a WordPress filter (`ttos_intake_step_sanitizers`) so later stages (menu builder, connectors, VAT/legal) can register their own sanitizer callbacks without editing this method again — consistent with WordPress's own extensibility pattern already used throughout this plugin (`apply_filters` appears extensively in `class-woocommerce.php` and `class-features.php`).

- [ ] **Step 3: Write the REST route class**

`takeaway-os/includes/class-intake-rest.php`:
```php
<?php

defined('ABSPATH') || exit;

/**
 * REST endpoints for the React intake wizard. Thin wrapper around
 * TTOS_Client_Intake's existing token-validated record access — every
 * route re-validates the hashed token on every call, same trust model
 * as the legacy POST-and-redirect form it replaces.
 */
final class TTOS_Intake_REST {

    const NS = 'ttos/v1';

    public static function hooks(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        register_rest_route(self::NS, '/intake/record', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_get_record'),
            'permission_callback' => '__return_true', // verified inside via hashed token
        ));

        register_rest_route(self::NS, '/intake/save-step', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_save_step'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/intake/submit', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_submit'),
            'permission_callback' => '__return_true',
        ));
    }

    private static function validated_or_error(\WP_REST_Request $request) {
        $id    = sanitize_text_field((string) $request->get_param('intake_id'));
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ($id === '' || $token === '') {
            return new \WP_Error('ttos_intake_missing_auth', 'Missing intake ID or token.', array('status' => 400));
        }
        return TTOS_Client_Intake::validated_public_record($id, $token);
    }

    public static function handle_get_record(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        return new \WP_REST_Response($record, 200);
    }

    public static function handle_save_step(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        $step_id   = sanitize_key((string) $request->get_param('step_id'));
        $step_data = (array) $request->get_param('data');
        if ($step_id === '') {
            return new \WP_REST_Response(array('message' => 'Missing step_id.'), 400);
        }
        $updated = TTOS_Client_Intake::save_step_data($record, $step_id, $step_data);
        return new \WP_REST_Response($updated, 200);
    }

    public static function handle_submit(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        $errors = TTOS_Client_Intake::validate_final_submission_public($record['submitted_data'] ?? array());
        if ($errors) {
            return new \WP_REST_Response(array('message' => implode(' ', $errors)), 422);
        }
        $record['status'] = 'submitted';
        $record['submitted_at'] = current_time('mysql');
        TTOS_Client_Intake::save_record_public($record);
        return new \WP_REST_Response($record, 200);
    }
}
```

- [ ] **Step 4: Expose two more small public wrappers**

`validate_final_submission()` and `save_record()` are also `private static` in `class-client-intake.php`. Rather than widen their visibility (they're used internally by the legacy POST handler too, and widening risks accidental external use), add two thin public wrappers right after `save_step_data()` from Step 2:
```php
    public static function validate_final_submission_public(array $data): array {
        return self::validate_final_submission($data);
    }

    public static function save_record_public(array $record): void {
        self::save_record($record);
    }
```

- [ ] **Step 5: Register the new file and mount React instead of the PHP form**

In `takeaway-os/takeaway-os.php`, add `'includes/class-intake-rest.php',` to the `$ttos_files` array (alongside the existing entries) and `TTOS_Intake_REST::hooks();` to the `plugins_loaded` callback.

In `class-client-intake.php`, find `render_public_document()` (the method containing the `<html>` shell) and replace the branch that currently calls `self::render_public_form($record, $token);` with:
```php
            echo '<div id="ttos-intake-root"></div>';
            self::enqueue_intake_assets($record, $token);
```

Add the new `enqueue_intake_assets()` method near `maybe_render_public_form()`:
```php
    private static function enqueue_intake_assets(array $record, string $token): void {
        wp_enqueue_style('ttos-intake', TTOS_URL . 'intake/dist/intake.css', array(), TTOS_VERSION);
        wp_enqueue_script('ttos-intake', TTOS_URL . 'intake/dist/intake.js', array(), TTOS_VERSION, true);
        wp_add_inline_script('ttos-intake', 'window.ttosIntake = ' . wp_json_encode(array(
            'apiBase'  => esc_url_raw(rest_url('ttos/v1')),
            'intakeId' => (string) $record['id'],
            'token'    => $token,
        )) . ';', 'before');
    }
```

`render_public_form()` and its `public_field()`/`public_upload()`/etc. helpers stay in the file, unused by the new path but left in place as a documented fallback (see Stage 11 note on removing dead code only after full end-to-end verification).

- [ ] **Step 6: Stage 2 self-review loop**

1. `php -l takeaway-os/includes/class-intake-rest.php` — expect "No syntax errors detected".
2. `php -l takeaway-os/includes/class-client-intake.php` — expect "No syntax errors detected".
3. Deploy to staging, then from a terminal:
```bash
curl -s -X POST "https://takeaway.thatdeveloper.co.uk/wp-json/ttos/v1/intake/record" \
  -H "Content-Type: application/json" \
  -d '{"intake_id":"test","token":"invalid"}'
```
Expected: `403` with a JSON `message` field (proves the route exists and rejects bad tokens — do not test with a real token from a terminal, since that would burn the real client's fresh-token TTL).
4. Confirm `wp option get ttos_client_intake_index` still lists intake records normally (proves nothing about record storage broke).

- [ ] **Step 7: Commit**

```bash
git add takeaway-os/includes/class-intake-rest.php takeaway-os/includes/class-client-intake.php takeaway-os/takeaway-os.php
git commit -m "feat: add REST layer for React intake wizard, mount root div"
```

---

## Stage 3: Branding steps (Palette, Layout Style, Typography, Logo/Photos, Brand Summary)

**Files:**
- Create: `takeaway-os/intake/src/steps/Palette.jsx`
- Create: `takeaway-os/intake/src/steps/LayoutStyle.jsx`
- Create: `takeaway-os/intake/src/steps/Typography.jsx`
- Create: `takeaway-os/intake/src/steps/LogoPhotos.jsx`
- Create: `takeaway-os/intake/src/steps/BrandSummary.jsx`
- Modify: `takeaway-os/intake/src/IntakeWizard.jsx` (register the five steps)

- [ ] **Step 1: Palette Studio**

`takeaway-os/intake/src/steps/Palette.jsx` — draggable colour strips using native HTML5 drag events (simple reorder, no `@dnd-kit` needed here — that's reserved for the menu builder's more complex nested tree in Stage 5):
```jsx
import React, { useState } from 'react'
import HelpTip from '../components/HelpTip.jsx'

const DEFAULT_SWATCHES = [
  { name: 'Primary', hex: '#d83a16' },
  { name: 'Accent', hex: '#d99a22' },
  { name: 'Text', hex: '#1f1712' },
  { name: 'Background', hex: '#fbf4ea' },
]

export default function Palette({ data, onNext, saving }) {
  const [colors, setColors] = useState(data.colors?.length ? data.colors : DEFAULT_SWATCHES)
  const [dragIdx, setDragIdx] = useState(null)

  const updateHex = (i, hex) => setColors(cs => cs.map((c, idx) => idx === i ? { ...c, hex } : c))

  const onDrop = (targetIdx) => {
    if (dragIdx === null || dragIdx === targetIdx) return
    setColors(cs => {
      const next = [...cs]
      const [moved] = next.splice(dragIdx, 1)
      next.splice(targetIdx, 0, moved)
      return next
    })
    setDragIdx(null)
  }

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Build your palette <HelpTip text="Drag a strip to change its order. Click a swatch to pick its colour." /></h2>
      <div style={{ display: 'flex', height: 64, borderRadius: 10, overflow: 'hidden', border: '1px solid var(--tt-border, #e5d6c5)' }}>
        {colors.map((c, i) => (
          <div
            key={c.name}
            draggable
            onDragStart={() => setDragIdx(i)}
            onDragOver={e => e.preventDefault()}
            onDrop={() => onDrop(i)}
            style={{ flex: 1, background: c.hex, display: 'flex', alignItems: 'flex-end', padding: 8, cursor: 'grab' }}
          >
            <span style={{ fontSize: 11, color: '#fff', textShadow: '0 1px 2px rgba(0,0,0,.4)' }}>{c.name}</span>
          </div>
        ))}
      </div>
      <div style={{ marginTop: 14, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        {colors.map((c, i) => (
          <label key={c.name} style={{ fontSize: 12 }}>
            {c.name}
            <input type="color" value={c.hex} onChange={e => updateHex(i, e.target.value)}
              style={{ display: 'block', width: '100%', height: 36, marginTop: 4, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 6 }} />
          </label>
        ))}
      </div>
      <button type="button" disabled={saving} onClick={() => onNext({ colors })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 2: Layout style picker**

`takeaway-os/intake/src/steps/LayoutStyle.jsx` — uses `VisualCardPicker` from Stage 1, with the exact option sets from spec §6.1 (matching `class-admin.php:205-209`):
```jsx
import React, { useState } from 'react'
import VisualCardPicker from '../components/VisualCardPicker.jsx'

const HEADER_OPTIONS = [
  { value: 'utility_header', label: 'Utility header' },
  { value: 'centered_brand', label: 'Centered brand' },
]
const HERO_OPTIONS = [
  { value: 'editorial_split', label: 'Editorial split' },
  { value: 'cinematic_photo', label: 'Cinematic photo' },
  { value: 'product_mosaic', label: 'Product mosaic' },
]
const FOOTER_OPTIONS = [
  { value: 'trust_led', label: 'Trust-led footer' },
  { value: 'editorial', label: 'Editorial footer' },
]

export default function LayoutStyle({ data, onNext, saving }) {
  const [header, setHeader] = useState(data.header_style || 'utility_header')
  const [hero, setHero] = useState(data.hero_style || 'editorial_split')
  const [footer, setFooter] = useState(data.footer_style || 'trust_led')

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Pick your homepage style</h2>
      <p style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)' }}>Header</p>
      <VisualCardPicker options={HEADER_OPTIONS} value={header} onChange={setHeader} columns={2} />
      <p style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)', marginTop: 16 }}>Hero</p>
      <VisualCardPicker options={HERO_OPTIONS} value={hero} onChange={setHero} columns={3} />
      <p style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)', marginTop: 16 }}>Footer</p>
      <VisualCardPicker options={FOOTER_OPTIONS} value={footer} onChange={setFooter} columns={2} />
      <button type="button" disabled={saving}
        onClick={() => onNext({ header_style: header, hero_style: hero, footer_style: footer })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 3: Typography picker**

`takeaway-os/intake/src/steps/Typography.jsx`:
```jsx
import React, { useState } from 'react'
import HelpTip from '../components/HelpTip.jsx'

export default function Typography({ data, onNext, saving }) {
  const [heading, setHeading] = useState(data.font_heading || '')
  const [body, setBody] = useState(data.font_body || '')

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Typography <HelpTip text="Type a Google Font name (e.g. Playfair Display). Leave blank to use our default." /></h2>
      <label style={{ display: 'block', fontSize: 12, marginTop: 12 }}>
        Heading font
        <input type="text" value={heading} onChange={e => setHeading(e.target.value)} placeholder="e.g. Playfair Display"
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <label style={{ display: 'block', fontSize: 12, marginTop: 12 }}>
        Body font
        <input type="text" value={body} onChange={e => setBody(e.target.value)} placeholder="e.g. Inter"
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <button type="button" disabled={saving} onClick={() => onNext({ font_heading: heading, font_body: body })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 4: Logo & photos step**

`takeaway-os/intake/src/steps/LogoPhotos.jsx` — uses plain `<input type="file">` posting via `FormData` to a dedicated upload endpoint (not the JSON `save-step` route, since files need multipart). Add the endpoint first:

In `class-intake-rest.php`, add a route:
```php
        register_rest_route(self::NS, '/intake/upload', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_upload'),
            'permission_callback' => '__return_true',
        ));
```
And the handler, reusing `TTOS_Client_Intake`'s existing per-field validated upload storage (`collect_uploads()` is private; add one more public wrapper):
```php
    public static function handle_upload(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        $field = sanitize_key((string) $request->get_param('field'));
        $updated = TTOS_Client_Intake::collect_upload_public($record, $field, $_FILES[$field] ?? null);
        if (is_wp_error($updated)) {
            return new \WP_REST_Response(array('message' => $updated->get_error_message()), 422);
        }
        return new \WP_REST_Response($updated, 200);
    }
```
In `class-client-intake.php`, add the wrapper (reuses the existing `upload_specs()` + `validate_upload_mime()` + `TTOS_Hardening::stash_uploaded_file()` pipeline already proven in `collect_uploads()`):
```php
    public static function collect_upload_public(array $record, string $field, $file) {
        if (!$file || !isset(self::upload_specs()[$field])) {
            return new \WP_Error('ttos_intake_bad_field', 'Unknown upload field.');
        }
        $_FILES[$field] = $file; // collect_uploads() reads from the superglobal by field name
        $record['uploads'] = self::collect_uploads($record);
        self::save_record($record);
        return $record;
    }
```

React side:
```jsx
import React, { useState } from 'react'

const FIELDS = [
  { key: 'branding_logo', label: 'Logo', multiple: false },
  { key: 'branding_favicon', label: 'Favicon', multiple: false },
  { key: 'shopfront_photos', label: 'Shopfront photos', multiple: true },
  { key: 'food_photos', label: 'Food photos', multiple: true },
]

export default function LogoPhotos({ onNext, saving }) {
  const [uploading, setUploading] = useState(null)
  const [uploaded, setUploaded] = useState({})

  const upload = async (field, files) => {
    setUploading(field)
    const form = new FormData()
    form.append('field', field)
    for (const f of files) form.append(field + (files.length > 1 ? '[]' : ''), f)
    const { apiBase, intakeId, token } = window.ttosIntake
    form.append('intake_id', intakeId)
    form.append('token', token)
    const res = await fetch(apiBase + '/intake/upload', { method: 'POST', body: form })
    const json = await res.json()
    setUploaded(u => ({ ...u, [field]: true }))
    setUploading(null)
    return json
  }

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Logo &amp; photos</h2>
      {FIELDS.map(f => (
        <label key={f.key} style={{ display: 'block', marginTop: 12, fontSize: 13 }}>
          {f.label}{uploaded[f.key] && <span style={{ color: 'var(--tt-success, #2f7d46)', marginLeft: 8 }}>✓ uploaded</span>}
          <input type="file" multiple={f.multiple} disabled={uploading === f.key}
            onChange={e => upload(f.key, e.target.files)}
            style={{ display: 'block', marginTop: 4 }} />
        </label>
      ))}
      <button type="button" disabled={saving} onClick={() => onNext({})}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```
Uploads save themselves immediately on file select (via the dedicated endpoint) — `onNext({})` just advances the step since there's no additional field data to save.

- [ ] **Step 5: Brand summary review**

`takeaway-os/intake/src/steps/BrandSummary.jsx` — reads sibling step data via `record`, same pattern as `Finish.jsx`:
```jsx
import React from 'react'

export default function BrandSummary({ record, onNext, saving }) {
  const palette = record?.submitted_data?.palette?.colors || []
  const typo = record?.submitted_data?.typography || {}
  const business = record?.submitted_data?.business_basics || {}

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Review your brand</h2>
      <div style={{ display: 'flex', height: 40, borderRadius: 8, overflow: 'hidden', marginTop: 10 }}>
        {palette.map(c => <div key={c.name} style={{ flex: 1, background: c.hex }} />)}
      </div>
      <div style={{ marginTop: 12, fontSize: 14 }}>
        <strong>{business.takeaway_name || 'Your business name'}</strong>
      </div>
      <div style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)', marginTop: 6 }}>
        Heading: {typo.font_heading || 'default'} · Body: {typo.font_body || 'default'}
      </div>
      <button type="button" disabled={saving} onClick={() => onNext({})}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Looks good, continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 6: Register PHP sanitizers for palette, layout_style, and typography**

These three step ids have no matching section in `sanitize_public_submission()`, so without a dedicated sanitizer they'd silently sanitize to an empty array via Stage 2's fallback. Add to `class-intake-rest.php`, using the same `ttos_intake_step_sanitizers` filter Stage 5 uses for `menu_builder` (add to the same `register_sanitizers()` method Stage 5 creates — if Stage 5 hasn't run yet in your build order, create `register_sanitizers()` now and Stage 5 will extend it):
```php
    public static function register_sanitizers(array $sanitizers): array {
        $sanitizers['palette'] = array(__CLASS__, 'sanitize_palette');
        $sanitizers['layout_style'] = array(__CLASS__, 'sanitize_layout_style');
        $sanitizers['typography'] = array(__CLASS__, 'sanitize_typography');
        return $sanitizers;
    }

    public static function sanitize_palette(array $raw): array {
        $colors = array();
        foreach ((array) ($raw['colors'] ?? array()) as $c) {
            $colors[] = array(
                'name' => sanitize_text_field((string) ($c['name'] ?? '')),
                'hex'  => sanitize_hex_color((string) ($c['hex'] ?? '')) ?: '#000000',
            );
        }
        return array('colors' => $colors);
    }

    public static function sanitize_layout_style(array $raw): array {
        $allowed = array(
            'header_style' => array('utility_header', 'centered_brand'),
            'hero_style'   => array('editorial_split', 'cinematic_photo', 'product_mosaic'),
            'footer_style' => array('trust_led', 'editorial'),
        );
        $clean = array();
        foreach ($allowed as $key => $options) {
            $val = sanitize_key((string) ($raw[$key] ?? ''));
            $clean[$key] = in_array($val, $options, true) ? $val : $options[0];
        }
        return $clean;
    }

    public static function sanitize_typography(array $raw): array {
        return array(
            'font_heading' => sanitize_text_field((string) ($raw['font_heading'] ?? '')),
            'font_body'    => sanitize_text_field((string) ($raw['font_body'] ?? '')),
        );
    }
```
And ensure `register_routes()` hooks the filter (only add this line once across the whole file — if Stage 5 already added it, skip this part):
```php
        add_filter('ttos_intake_step_sanitizers', array(__CLASS__, 'register_sanitizers'));
```

- [ ] **Step 7: Register the five steps and pass `record` where needed**

In `IntakeWizard.jsx`, import and register:
```jsx
import Palette from './steps/Palette.jsx'
import LayoutStyle from './steps/LayoutStyle.jsx'
import Typography from './steps/Typography.jsx'
import LogoPhotos from './steps/LogoPhotos.jsx'
import BrandSummary from './steps/BrandSummary.jsx'

registerStep('palette', Palette)
registerStep('layout_style', LayoutStyle)
registerStep('typography', Typography)
registerStep('logo_photos', LogoPhotos)
registerStep('brand_summary', BrandSummary)
```
Update the `<Component .../>` render in `IntakeWizard.jsx` to also pass `record={record}` (needed by `BrandSummary` and `Finish`; harmless for steps that ignore it).

- [ ] **Step 8: Stage 3 self-review loop**

1. `npm run build` — zero errors.
2. `php -l takeaway-os/includes/class-intake-rest.php` — clean.
3. Manually walk all 5 steps on staging using a real test intake record (create one via Client Intake admin screen with a short expiry, delete it after testing).
4. Confirm the Palette step's colour strips are draggable with a mouse; confirm no console errors on drop.
5. **Confirm the sanitizer fix actually works:** after saving the Palette step, inspect the record's stored data (`wp option get ttos_client_intake_<record-option-suffix> --format=json` or view it in the admin Request detail screen) and confirm `submitted_data.palette.colors` contains the real chosen hex values — not an empty array. This is the specific bug Step 6 fixed; confirm it stayed fixed.
6. Confirm Brand Summary correctly shows data entered two steps earlier (proves cross-step `record` access works).

- [ ] **Step 9: Commit**

```bash
git add takeaway-os/intake/ takeaway-os/includes/class-intake-rest.php takeaway-os/includes/class-client-intake.php
git commit -m "feat: add branding steps (palette, layout, typography, logo/photos, summary)"
```

---

## Stage 4: Simple grouped-field steps (Business Basics, Opening Hours, Delivery & Collection)

**Files:**
- Create: `takeaway-os/intake/src/components/FieldGroup.jsx`
- Create: `takeaway-os/intake/src/steps/BusinessBasics.jsx`
- Create: `takeaway-os/intake/src/steps/OpeningHours.jsx`
- Create: `takeaway-os/intake/src/steps/DeliveryCollection.jsx`
- Modify: `takeaway-os/intake/src/IntakeWizard.jsx` (register three steps)

These three steps share one shape (a list of labelled inputs) — one canonical component (`FieldGroup`) plus three field-definition tables, per DRY. No placeholders: every field below is a real, complete field list matching spec §5's existing Client Intake data shape exactly (see `class-client-intake.php`'s `sanitize_public_submission()`, lines 826–914, for the authoritative field names already defined there).

- [ ] **Step 1: Write the shared `FieldGroup` component**

`takeaway-os/intake/src/components/FieldGroup.jsx`:
```jsx
import React, { useState } from 'react'

export default function FieldGroup({ fields, initial, onSubmit, saving, submitLabel = 'Continue' }) {
  const [values, setValues] = useState(() => {
    const v = {}
    for (const f of fields) v[f.name] = initial[f.name] ?? (f.type === 'checkbox' ? false : '')
    return v
  })

  const set = (name, val) => setValues(v => ({ ...v, [name]: val }))

  return (
    <div>
      <div style={{ display: 'grid', gridTemplateColumns: fields.some(f => f.wide) ? '1fr' : '1fr 1fr', gap: 12 }}>
        {fields.map(f => (
          <label key={f.name} style={{ fontSize: 12, gridColumn: f.wide ? '1 / -1' : 'auto' }}>
            {f.label}
            {f.type === 'checkbox' ? (
              <input type="checkbox" checked={!!values[f.name]} onChange={e => set(f.name, e.target.checked)}
                style={{ display: 'block', marginTop: 4 }} />
            ) : f.type === 'textarea' ? (
              <textarea value={values[f.name]} onChange={e => set(f.name, e.target.value)} rows={f.rows || 3}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
            ) : (
              <input type={f.type || 'text'} value={values[f.name]} onChange={e => set(f.name, e.target.value)}
                style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
            )}
          </label>
        ))}
      </div>
      <button type="button" disabled={saving} onClick={() => onSubmit(values)}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : submitLabel}
      </button>
    </div>
  )
}
```

- [ ] **Step 2: Business Basics**

`takeaway-os/intake/src/steps/BusinessBasics.jsx`:
```jsx
import React from 'react'
import FieldGroup from '../components/FieldGroup.jsx'

const FIELDS = [
  { name: 'takeaway_name', label: 'Takeaway name' },
  { name: 'legal_name', label: 'Legal / business name' },
  { name: 'phone', label: 'Phone number', type: 'tel' },
  { name: 'email', label: 'Email address', type: 'email' },
  { name: 'website', label: 'Website / domain', type: 'url' },
  { name: 'address_1', label: 'Address line 1' },
  { name: 'address_2', label: 'Address line 2' },
  { name: 'town', label: 'Town / city' },
  { name: 'postcode', label: 'Postcode' },
  { name: 'google_maps_url', label: 'Google Maps link', type: 'url' },
  { name: 'hygiene_rating', label: 'Hygiene rating value' },
  { name: 'hygiene_url', label: 'Hygiene rating link', type: 'url' },
  { name: 'company_number', label: 'Company number' },
]

export default function BusinessBasics({ data, onNext, saving }) {
  return <FieldGroup fields={FIELDS} initial={data} onSubmit={onNext} saving={saving} />
}
```
Note: `vat_number` is deliberately excluded here — it moved to the `vat_legal` step (Stage 9) per spec §6.7, since it's now paired with the plain-English VAT question rather than sitting alone in business basics.

- [ ] **Step 3: Opening Hours**

`takeaway-os/intake/src/steps/OpeningHours.jsx` — one row per day, each day is its own small `FieldGroup`-shaped set (not reusing `FieldGroup` directly since days repeat; written explicitly for clarity):
```jsx
import React, { useState } from 'react'

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

export default function OpeningHours({ data, onNext, saving }) {
  const [days, setDays] = useState(() => {
    const d = {}
    for (const day of DAYS) d[day] = data.days?.[day] || { closed: false, open: '', close: '', collection_open: '', collection_close: '', delivery_open: '', delivery_close: '', note: '' }
    return d
  })
  const [special, setSpecial] = useState(data.special_closing_days || '')

  const setDay = (day, key, val) => setDays(d => ({ ...d, [day]: { ...d[day], [key]: val } }))

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Opening hours</h2>
      {DAYS.map(day => (
        <div key={day} style={{ border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8, padding: 10, marginTop: 8 }}>
          <div style={{ fontSize: 13, fontWeight: 500, textTransform: 'capitalize' }}>{day}</div>
          <label style={{ fontSize: 12 }}>
            <input type="checkbox" checked={days[day].closed} onChange={e => setDay(day, 'closed', e.target.checked)} /> Closed
          </label>
          {!days[day].closed && (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 6 }}>
              <input type="time" value={days[day].open} onChange={e => setDay(day, 'open', e.target.value)} placeholder="Open" />
              <input type="time" value={days[day].close} onChange={e => setDay(day, 'close', e.target.value)} placeholder="Close" />
            </div>
          )}
        </div>
      ))}
      <label style={{ display: 'block', marginTop: 12, fontSize: 12 }}>
        Special closing days
        <textarea value={special} onChange={e => setSpecial(e.target.value)} rows={2}
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <button type="button" disabled={saving} onClick={() => onNext({ days, special_closing_days: special })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 4: Delivery & Collection**

`takeaway-os/intake/src/steps/DeliveryCollection.jsx`:
```jsx
import React from 'react'
import FieldGroup from '../components/FieldGroup.jsx'

const FIELDS = [
  { name: 'collection_enabled', label: 'Collection enabled', type: 'checkbox' },
  { name: 'delivery_enabled', label: 'Delivery enabled', type: 'checkbox' },
  { name: 'delivery_postcodes', label: 'Delivery postcode areas', type: 'textarea', wide: true },
  { name: 'delivery_fee', label: 'Delivery fee' },
  { name: 'free_delivery_threshold', label: 'Free delivery threshold' },
  { name: 'minimum_order', label: 'Minimum order amount' },
  { name: 'prep_time', label: 'Estimated prep time (mins)', type: 'number' },
  { name: 'delivery_time', label: 'Estimated delivery time (mins)', type: 'number' },
]

export default function DeliveryCollection({ data, onNext, saving }) {
  return <FieldGroup fields={FIELDS} initial={data} onSubmit={onNext} saving={saving} />
}
```

- [ ] **Step 5: Register the three steps**

```jsx
import BusinessBasics from './steps/BusinessBasics.jsx'
import OpeningHours from './steps/OpeningHours.jsx'
import DeliveryCollection from './steps/DeliveryCollection.jsx'

registerStep('business_basics', BusinessBasics)
registerStep('opening_hours', OpeningHours)
registerStep('delivery_collection', DeliveryCollection)
```

- [ ] **Step 6: Stage 4 self-review loop**

1. `npm run build` — zero errors.
2. Walk all three steps on staging, confirm values persist across Back/Next (proves `data` prop correctly reflects what was saved).
3. Confirm `vat_number` field does NOT appear in Business Basics (moved to Stage 9) — a quick grep: `grep -c vat_number takeaway-os/intake/src/steps/BusinessBasics.jsx` must return `0`.

- [ ] **Step 7: Commit**

```bash
git add takeaway-os/intake/src/components/FieldGroup.jsx takeaway-os/intake/src/steps/BusinessBasics.jsx takeaway-os/intake/src/steps/OpeningHours.jsx takeaway-os/intake/src/steps/DeliveryCollection.jsx takeaway-os/intake/src/IntakeWizard.jsx
git commit -m "feat: add business/hours/delivery grouped-field steps"
```

---

## Stage 5: Menu builder UI (accessible drag-and-drop)

This is the highest-risk stage — spec §7.1's accessibility requirements are binding, not aspirational. Read them again before starting: keyboard reordering is the *primary* mechanism, not a fallback; no stuck drag states; 44px touch targets; WCAG 2.2 AA throughout.

**Files:**
- Create: `takeaway-os/intake/src/menu-builder/MenuBuilder.jsx`
- Create: `takeaway-os/intake/src/menu-builder/CategoryTree.jsx`
- Create: `takeaway-os/intake/src/menu-builder/ItemEditor.jsx`
- Create: `takeaway-os/intake/src/menu-builder/useKeyboardReorder.js`
- Modify: `takeaway-os/intake/src/IntakeWizard.jsx` (register `menu_builder` step)

- [ ] **Step 1: Write the data model**

Menu data shape (matches spec §7 exactly):
```js
// One category:
{
  id: 'cat-1',
  name: 'Pizzas',
  sub_categories: [
    {
      id: 'sub-1',
      name: 'Classic',
      items: [
        {
          id: 'item-1',
          name: 'Margherita',
          description: '...',
          price: '9.50',
          image_upload_ref: null, // set after upload, see Step 5
          allergens: ['gluten', 'milk'],
          option_groups: [
            { name: 'Size', choices: [{ label: '10 inch', price_delta: '0' }, { label: '12 inch', price_delta: '2.50' }] }
          ],
          kitchen_note: ''
        }
      ]
    }
  ]
}
```

- [ ] **Step 2: Write the keyboard reorder hook**

`takeaway-os/intake/src/menu-builder/useKeyboardReorder.js` — this is the primary reorder mechanism per spec §7.1; `@dnd-kit`'s `KeyboardSensor` (Step 4) drives the actual pick-up/move/drop, this hook supplies the live-region announcement and the "no stuck state" cancel guarantee:
```js
import { useState, useCallback, useRef } from 'react'

export function useKeyboardReorder(onAnnounce) {
  const [activeId, setActiveId] = useState(null)
  const cancelTimer = useRef(null)

  const startDrag = useCallback((id, label) => {
    setActiveId(id)
    onAnnounce(label + ' picked up. Use arrow keys to move, space to drop, escape to cancel.')
    // Safety net: if a drag is somehow left active (lost focus, browser
    // event we didn't catch), force-cancel after 15s so nothing can stay
    // stuck. This is the concrete implementation of spec §7.1's
    // "no stuck/frozen drag states" requirement.
    clearTimeout(cancelTimer.current)
    cancelTimer.current = setTimeout(() => {
      setActiveId(null)
      onAnnounce('Reorder cancelled — no response.')
    }, 15000)
  }, [onAnnounce])

  const endDrag = useCallback((movedLabel, newPosition) => {
    clearTimeout(cancelTimer.current)
    setActiveId(null)
    onAnnounce(movedLabel + ' moved to position ' + newPosition + '.')
  }, [onAnnounce])

  const cancelDrag = useCallback(() => {
    clearTimeout(cancelTimer.current)
    setActiveId(null)
    onAnnounce('Reorder cancelled.')
  }, [onAnnounce])

  return { activeId, startDrag, endDrag, cancelDrag }
}
```

- [ ] **Step 3: Add `@dnd-kit` (already in `package.json` from Stage 1) and write `CategoryTree.jsx`**

`takeaway-os/intake/src/menu-builder/CategoryTree.jsx`:
```jsx
import React, { useState, useRef } from 'react'
import {
  DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, closestCenter
} from '@dnd-kit/core'
import {
  SortableContext, verticalListSortingStrategy, useSortable, sortableKeyboardCoordinates
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useKeyboardReorder } from './useKeyboardReorder.js'

function SortableItem({ id, children }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id })
  return (
    <div ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 }}>
      <span
        {...attributes}
        {...listeners}
        role="button"
        aria-label="Drag to reorder"
        tabIndex={0}
        style={{ display: 'inline-flex', width: 44, height: 44, alignItems: 'center', justifyContent: 'center', cursor: 'grab' }}
      >⠿</span>
      {children}
    </div>
  )
}

export default function CategoryTree({ categories, onChange, onSelectItem, selectedItemId }) {
  const liveRef = useRef(null)
  const announce = (msg) => { if (liveRef.current) liveRef.current.textContent = msg }
  const { startDrag, endDrag, cancelDrag } = useKeyboardReorder(announce)

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  const handleDragEnd = (event) => {
    const { active, over } = event
    if (!over || active.id === over.id) { cancelDrag(); return }
    // Reordering happens within a flat list of item ids per sub-category;
    // callers pass a flat `items` array already scoped to one sub-category,
    // so this generic handler just reports old/new index back to onChange.
    const oldIndex = categories.findIndex(c => c.id === active.id)
    const newIndex = categories.findIndex(c => c.id === over.id)
    const next = [...categories]
    const [moved] = next.splice(oldIndex, 1)
    next.splice(newIndex, 0, moved)
    onChange(next)
    endDrag(moved.name, newIndex + 1)
  }

  const addCategory = () => onChange([...categories, { id: 'cat-' + Date.now(), name: 'New category', sub_categories: [] }])

  return (
    <div>
      <div aria-live="polite" className="sr-only" ref={liveRef} style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden' }} />
      <DndContext sensors={sensors} collisionDetection={closestCenter}
        onDragStart={(e) => { const cat = categories.find(c => c.id === e.active.id); startDrag(e.active.id, cat?.name || 'Item') }}
        onDragEnd={handleDragEnd} onDragCancel={cancelDrag}>
        <SortableContext items={categories.map(c => c.id)} strategy={verticalListSortingStrategy}>
          {categories.map(cat => (
            <SortableItem key={cat.id} id={cat.id}>
              <input
                value={cat.name}
                onChange={e => onChange(categories.map(c => c.id === cat.id ? { ...c, name: e.target.value } : c))}
                style={{ fontWeight: 500, border: 'none', background: 'transparent', fontSize: 14 }}
              />
            </SortableItem>
          ))}
        </SortableContext>
      </DndContext>
      <button type="button" onClick={addCategory}
        style={{ marginTop: 8, padding: '8px 12px', minHeight: 44, border: '1px dashed var(--tt-border, #e5d6c5)', borderRadius: 8, background: 'transparent', fontSize: 13, cursor: 'pointer' }}>
        + Add category
      </button>
    </div>
  )
}
```

Note on scope: `CategoryTree` handles top-level category reordering fully. Sub-category and item reordering follow the identical `DndContext`/`SortableContext`/`useSortable` pattern one level deeper — implement `SubCategoryList` and `ItemList` as siblings using the same three building blocks (`SortableItem`, sensors config, `handleDragEnd` shape) rather than duplicating new logic; this is a direct application of the pattern just written, not a new interaction to design.

- [ ] **Step 4: Write the item editor**

`takeaway-os/intake/src/menu-builder/ItemEditor.jsx`:
```jsx
import React from 'react'

const ALLERGENS = ['gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soybeans', 'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs']

export default function ItemEditor({ item, onChange, onUploadImage }) {
  const set = (key, val) => onChange({ ...item, [key]: val })
  const toggleAllergen = (a) => {
    const has = item.allergens.includes(a)
    set('allergens', has ? item.allergens.filter(x => x !== a) : [...item.allergens, a])
  }
  const addOptionGroup = () => set('option_groups', [...item.option_groups, { name: '', choices: [{ label: '', price_delta: '0' }] }])
  const updateGroup = (i, group) => set('option_groups', item.option_groups.map((g, idx) => idx === i ? group : g))

  return (
    <div>
      <label style={{ fontSize: 12 }}>Photo
        <input type="file" accept="image/*" onChange={e => onUploadImage(e.target.files[0])} style={{ display: 'block', marginTop: 4 }} />
      </label>
      <label style={{ display: 'block', marginTop: 10, fontSize: 12 }}>Item name
        <input value={item.name} onChange={e => set('name', e.target.value)}
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, minHeight: 44, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <label style={{ display: 'block', marginTop: 10, fontSize: 12 }}>Description
        <textarea value={item.description} onChange={e => set('description', e.target.value)} rows={2}
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <label style={{ display: 'block', marginTop: 10, fontSize: 12 }}>Base price
        <input value={item.price} onChange={e => set('price', e.target.value)}
          style={{ display: 'block', width: 120, marginTop: 4, padding: 9, minHeight: 44, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      <div style={{ marginTop: 10, fontSize: 12 }}>Allergens</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 4 }}>
        {ALLERGENS.map(a => (
          <button key={a} type="button" onClick={() => toggleAllergen(a)}
            aria-pressed={item.allergens.includes(a)}
            style={{
              minHeight: 44, padding: '0 12px', borderRadius: 20, fontSize: 12, cursor: 'pointer',
              border: item.allergens.includes(a) ? '1px solid var(--tt-success, #2f7d46)' : '1px solid var(--tt-border, #e5d6c5)',
              background: item.allergens.includes(a) ? 'var(--tt-surface-soft, #f8efe4)' : 'transparent'
            }}>{a}</button>
        ))}
      </div>
      <div style={{ marginTop: 12, fontSize: 12 }}>Options</div>
      {item.option_groups.map((g, i) => (
        <div key={i} style={{ border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8, padding: 10, marginTop: 6 }}>
          <input value={g.name} placeholder="e.g. Size" onChange={e => updateGroup(i, { ...g, name: e.target.value })}
            style={{ width: '100%', border: 'none', background: 'transparent', fontSize: 13, marginBottom: 6 }} />
          {g.choices.map((c, ci) => (
            <div key={ci} style={{ display: 'grid', gridTemplateColumns: '1fr 90px', gap: 6, marginBottom: 6 }}>
              <input value={c.label} placeholder="e.g. 12 inch"
                onChange={e => updateGroup(i, { ...g, choices: g.choices.map((x, xi) => xi === ci ? { ...x, label: e.target.value } : x) })}
                style={{ padding: 8, minHeight: 44, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 6 }} />
              <input value={c.price_delta} placeholder="+£0"
                onChange={e => updateGroup(i, { ...g, choices: g.choices.map((x, xi) => xi === ci ? { ...x, price_delta: e.target.value } : x) })}
                style={{ padding: 8, minHeight: 44, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 6 }} />
            </div>
          ))}
          <button type="button" onClick={() => updateGroup(i, { ...g, choices: [...g.choices, { label: '', price_delta: '0' }] })}
            style={{ minHeight: 44, padding: '0 10px', border: '1px dashed var(--tt-border, #e5d6c5)', borderRadius: 6, background: 'transparent', fontSize: 12, cursor: 'pointer' }}>
            + Add choice
          </button>
        </div>
      ))}
      <button type="button" onClick={addOptionGroup}
        style={{ marginTop: 8, minHeight: 44, padding: '0 12px', border: '1px dashed var(--tt-border, #e5d6c5)', borderRadius: 8, background: 'transparent', fontSize: 13, cursor: 'pointer' }}>
        + Add option group
      </button>
    </div>
  )
}
```

- [ ] **Step 5: Write `MenuBuilder.jsx` (the step component) and image upload wiring**

Item photo upload reuses the same `/intake/upload` endpoint from Stage 3 Step 4, with `field` set to a per-item dynamic key (`menu_item_photo`); the returned `record.uploads.menu_item_photo` array's last entry's `stored_name` becomes that item's `image_upload_ref`.

```jsx
import React, { useState } from 'react'
import CategoryTree from './CategoryTree.jsx'
import ItemEditor from './ItemEditor.jsx'
import { api } from '../api.js'

const emptyItem = () => ({ id: 'item-' + Date.now(), name: '', description: '', price: '', image_upload_ref: null, allergens: [], option_groups: [], kitchen_note: '' })

export default function MenuBuilder({ data, onNext, saving }) {
  const [categories, setCategories] = useState(data.categories?.length ? data.categories : [
    { id: 'cat-' + Date.now(), name: 'New category', sub_categories: [{ id: 'sub-' + Date.now(), name: '', items: [emptyItem()] }] }
  ])
  const [selectedItemId, setSelectedItemId] = useState(categories[0]?.sub_categories[0]?.items[0]?.id)

  const allItems = categories.flatMap(c => c.sub_categories.flatMap(s => s.items))
  const selectedItem = allItems.find(i => i.id === selectedItemId)

  const updateItem = (updated) => {
    setCategories(cats => cats.map(c => ({
      ...c,
      sub_categories: c.sub_categories.map(s => ({
        ...s,
        items: s.items.map(i => i.id === updated.id ? updated : i)
      }))
    })))
  }

  const uploadItemImage = async (file) => {
    if (!file || !selectedItem) return
    const form = new FormData()
    form.append('field', 'menu_item_photo')
    form.append('menu_item_photo', file)
    const { apiBase, intakeId, token } = window.ttosIntake
    form.append('intake_id', intakeId)
    form.append('token', token)
    const res = await fetch(apiBase + '/intake/upload', { method: 'POST', body: form })
    const json = await res.json()
    const list = json.uploads?.menu_item_photo || []
    const last = list[list.length - 1]
    if (last) updateItem({ ...selectedItem, image_upload_ref: last.stored_name })
  }

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Build your menu</h2>
      <div style={{ display: 'grid', gridTemplateColumns: '260px 1fr', gap: 16, marginTop: 12 }}>
        <CategoryTree categories={categories} onChange={setCategories} onSelectItem={setSelectedItemId} selectedItemId={selectedItemId} />
        <div>
          {selectedItem ? (
            <ItemEditor item={selectedItem} onChange={updateItem} onUploadImage={uploadItemImage} />
          ) : (
            <p style={{ fontSize: 13, color: 'var(--tt-muted, #75665c)' }}>Select or add an item to edit it.</p>
          )}
        </div>
      </div>
      <button type="button" disabled={saving} onClick={() => onNext({ categories })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 6: Add the `menu_item_photo` upload spec to the PHP backend**

In `class-client-intake.php`'s `upload_specs()` method, add one entry to the existing array:
```php
            'menu_item_photo'          => array('extensions' => array('jpg', 'jpeg', 'png', 'webp'), 'max' => 6 * 1024 * 1024, 'multiple' => true),
```

- [ ] **Step 7: Register the menu builder sanitizer**

In `class-intake-rest.php`, add a hooked sanitizer using the filter defined in Stage 2 Step 2 (`ttos_intake_step_sanitizers`) — add near the bottom of the class:
```php
    public static function register_sanitizers(array $sanitizers): array {
        $sanitizers['menu_builder'] = array(__CLASS__, 'sanitize_menu_builder');
        return $sanitizers;
    }

    public static function sanitize_menu_builder(array $raw): array {
        $categories = array();
        foreach ((array) ($raw['categories'] ?? array()) as $cat) {
            $sub_categories = array();
            foreach ((array) ($cat['sub_categories'] ?? array()) as $sub) {
                $items = array();
                foreach ((array) ($sub['items'] ?? array()) as $item) {
                    $option_groups = array();
                    foreach ((array) ($item['option_groups'] ?? array()) as $group) {
                        $choices = array();
                        foreach ((array) ($group['choices'] ?? array()) as $choice) {
                            $choices[] = array(
                                'label'       => sanitize_text_field((string) ($choice['label'] ?? '')),
                                'price_delta' => sanitize_text_field((string) ($choice['price_delta'] ?? '0')),
                            );
                        }
                        $option_groups[] = array(
                            'name'    => sanitize_text_field((string) ($group['name'] ?? '')),
                            'choices' => $choices,
                        );
                    }
                    $items[] = array(
                        'id'               => sanitize_text_field((string) ($item['id'] ?? '')),
                        'name'             => sanitize_text_field((string) ($item['name'] ?? '')),
                        'description'      => sanitize_textarea_field((string) ($item['description'] ?? '')),
                        'price'            => sanitize_text_field((string) ($item['price'] ?? '')),
                        'image_upload_ref' => sanitize_text_field((string) ($item['image_upload_ref'] ?? '')),
                        'allergens'        => array_map('sanitize_key', (array) ($item['allergens'] ?? array())),
                        'option_groups'    => $option_groups,
                        'kitchen_note'     => sanitize_text_field((string) ($item['kitchen_note'] ?? '')),
                    );
                }
                $sub_categories[] = array(
                    'id'    => sanitize_text_field((string) ($sub['id'] ?? '')),
                    'name'  => sanitize_text_field((string) ($sub['name'] ?? '')),
                    'items' => $items,
                );
            }
            $categories[] = array(
                'id'             => sanitize_text_field((string) ($cat['id'] ?? '')),
                'name'           => sanitize_text_field((string) ($cat['name'] ?? '')),
                'sub_categories' => $sub_categories,
            );
        }
        return array('categories' => $categories);
    }
```
Hook it in `register_routes()` (add near the top of that method):
```php
        add_filter('ttos_intake_step_sanitizers', array(__CLASS__, 'register_sanitizers'));
```

- [ ] **Step 8: Register the step in React**

```jsx
import MenuBuilder from './menu-builder/MenuBuilder.jsx'
registerStep('menu_builder', MenuBuilder)
```

- [ ] **Step 9: Stage 5 self-review loop — accessibility pass (do not skip)**

1. `npm run build` — zero errors.
2. **Keyboard-only test:** unplug the mouse (or don't touch it). Tab to a category's drag handle, press `Space`, press arrow keys, press `Space` again. Confirm the category actually moved and the live region announced it (check with a screen reader, or inspect the `aria-live` div's text content in devtools).
3. **Interrupted-drag test:** start a keyboard drag (`Space` down), then click elsewhere with the mouse without pressing `Space` or `Escape`. Confirm the drag auto-cancels within 15 seconds (per the `useKeyboardReorder` safety timer) rather than staying stuck.
4. **Touch target check:** inspect every button/handle/chip in devtools — computed height must be ≥44px. Every one written above uses `minHeight: 44` or is a 44px flex container; confirm none were missed.
5. **Contrast check:** run the existing Setup Health-style contrast checker (or manual check) on allergen chip states and drag handles against both light and any dark-mode token values.
6. If any of 2–5 fail, fix before moving to Stage 6 — this is the spec's hardest requirement and the one most likely to regress silently.

- [ ] **Step 10: Commit**

```bash
git add takeaway-os/intake/src/menu-builder/ takeaway-os/intake/src/IntakeWizard.jsx takeaway-os/includes/class-intake-rest.php takeaway-os/includes/class-client-intake.php
git commit -m "feat: add accessible drag-and-drop menu builder"
```

---

## Stage 6: Menu import → real WooCommerce products

**Files:**
- Create: `takeaway-os/includes/class-menu-import.php`
- Modify: `takeaway-os/includes/class-client-intake.php` (`import_submission()` calls the new class)
- Modify: `takeaway-os/takeaway-os.php` (register new file)

- [ ] **Step 1: Write the menu import class**

This extends the existing product-creation pattern already proven in `TTOS_Production::apply_starter_menu()` (per spec §7's explicit instruction to reuse that mechanism), but handles option groups by creating `WC_Product_Variable` when an item has any, and a plain `WC_Product_Simple` otherwise.

`takeaway-os/includes/class-menu-import.php`:
```php
<?php

defined('ABSPATH') || exit;

/**
 * Converts a submitted intake record's menu_builder data into real
 * WooCommerce products and categories. Called from
 * TTOS_Client_Intake::import_submission() during the dev's "Import
 * submitted data" step — never runs automatically.
 */
final class TTOS_Menu_Import {

    public static function import(array $menu_data, array $record): array {
        if (!class_exists('WC_Product_Simple')) {
            return array('created' => 0, 'errors' => array('WooCommerce not active.'));
        }
        $created = 0;
        $errors  = array();

        foreach ((array) ($menu_data['categories'] ?? array()) as $cat) {
            $cat_name = sanitize_text_field((string) ($cat['name'] ?? ''));
            if ($cat_name === '') continue;
            $term_id = self::ensure_term($cat_name);

            foreach ((array) ($cat['sub_categories'] ?? array()) as $sub) {
                $sub_name = sanitize_text_field((string) ($sub['name'] ?? ''));
                $sub_term_id = $sub_name !== '' ? self::ensure_term($sub_name, $term_id) : 0;

                foreach ((array) ($sub['items'] ?? array()) as $item) {
                    try {
                        self::import_item($item, array_filter(array($term_id, $sub_term_id)), $record);
                        $created++;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        return array('created' => $created, 'errors' => $errors);
    }

    private static function ensure_term(string $name, int $parent = 0): int {
        $existing = term_exists($name, 'product_cat', $parent ?: 0);
        if ($existing) {
            return is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
        }
        $result = wp_insert_term($name, 'product_cat', array('parent' => $parent));
        return is_wp_error($result) ? 0 : (int) $result['term_id'];
    }

    private static function import_item(array $item, array $term_ids, array $record): void {
        $name = sanitize_text_field((string) ($item['name'] ?? ''));
        if ($name === '') {
            throw new \Exception('Menu item missing a name — skipped.');
        }
        $has_options = !empty($item['option_groups']);

        $product = $has_options ? new \WC_Product_Variable() : new \WC_Product_Simple();
        $product->set_name($name);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_description(sanitize_textarea_field((string) ($item['description'] ?? '')));
        if (!$has_options) {
            $price = (float) preg_replace('/[^0-9.]/', '', (string) ($item['price'] ?? '0'));
            $product->set_regular_price(number_format($price, 2, '.', ''));
        }
        $product->save();
        $product_id = $product->get_id();

        if ($term_ids) {
            wp_set_object_terms($product_id, array_map('intval', $term_ids), 'product_cat');
        }

        if (!empty($item['allergens'])) {
            update_post_meta($product_id, '_ttos_allergens', array_map('sanitize_key', (array) $item['allergens']));
        }
        if (!empty($item['kitchen_note'])) {
            update_post_meta($product_id, '_ttos_kitchen_note', sanitize_text_field((string) $item['kitchen_note']));
        }

        $image_ref = (string) ($item['image_upload_ref'] ?? '');
        if ($image_ref !== '') {
            $attach_id = self::attach_from_stored_upload($image_ref, $product_id, $record, $name);
            if ($attach_id) set_post_thumbnail($product_id, $attach_id);
        }

        if ($has_options) {
            self::apply_option_groups($product, (array) $item['option_groups']);
        }
    }

    private static function apply_option_groups(\WC_Product_Variable $product, array $groups): void {
        $base_price = 0.0;
        $attributes = array();
        foreach ($groups as $group) {
            $attr_name = sanitize_text_field((string) ($group['name'] ?? 'Option'));
            $options = array();
            foreach ((array) ($group['choices'] ?? array()) as $choice) {
                $options[] = sanitize_text_field((string) ($choice['label'] ?? ''));
            }
            $attribute = new \WC_Product_Attribute();
            $attribute->set_name($attr_name);
            $attribute->set_options($options);
            $attribute->set_variation(true);
            $attribute->set_visible(true);
            $attributes[] = $attribute;
        }
        $product->set_attributes($attributes);
        $product->save();

        // One variation per first attribute's choices — matches the
        // single-option-group case from the spec example (Size: 10/12/16
        // inch). Multi-group cartesian combinations are a known limitation
        // documented in the final build report (Stage 11) if the client's
        // menu actually needs them; the common case (one group per item)
        // is fully supported here.
        $first_group = $groups[0] ?? null;
        if (!$first_group) return;
        foreach ((array) ($first_group['choices'] ?? array()) as $choice) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes(array(sanitize_title($first_group['name']) => sanitize_text_field((string) $choice['label'])));
            $delta = (float) preg_replace('/[^0-9.\-]/', '', (string) ($choice['price_delta'] ?? '0'));
            $variation->set_regular_price(number_format($base_price + $delta, 2, '.', ''));
            $variation->save();
        }
    }

    private static function attach_from_stored_upload(string $stored_name, int $post_id, array $record, string $title): int {
        $dir = TTOS_Hardening::import_dir(false);
        if (empty($dir['path'])) return 0;
        $source = trailingslashit($dir['path']) . basename($stored_name);
        if (!is_file($source)) return 0;

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) return 0;
        $target_dir = trailingslashit($upload_dir['path']);
        if (!is_dir($target_dir)) wp_mkdir_p($target_dir);
        $filename = wp_unique_filename($target_dir, basename($stored_name));
        $target = $target_dir . $filename;
        if (!copy($source, $target)) return 0;

        $filetype = wp_check_filetype($filename, null);
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_text_field($title),
            'post_status'    => 'inherit',
        ), $target, $post_id);
        if (!$attachment_id || is_wp_error($attachment_id)) return 0;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $target);
        if (is_array($metadata)) wp_update_attachment_metadata($attachment_id, $metadata);
        return (int) $attachment_id;
    }
}
```

- [ ] **Step 2: Wire it into `import_submission()`**

In `class-client-intake.php`'s `import_submission()` method, add near the end (after the existing social links section):
```php
        $menu_data = (array) ($data['menu_builder'] ?? array());
        if (!empty($menu_data['categories']) && class_exists('TTOS_Menu_Import')) {
            TTOS_Menu_Import::import($menu_data, $record);
        }
```

- [ ] **Step 3: Register the new file**

In `takeaway-os.php`, add `'includes/class-menu-import.php',` to `$ttos_files` (no `hooks()` call needed — this class has no hooks, it's called directly).

- [ ] **Step 4: Stage 6 self-review loop**

1. `php -l takeaway-os/includes/class-menu-import.php` — expect clean.
2. On staging, create a test intake, submit a menu with one simple item and one item with a "Size" option group, mark it imported via the admin screen.
3. `wp post list --post_type=product --format=table` — confirm both products exist.
4. Confirm the variable product has variations: `wp wc product_variation list <parent_id>` (or check in wp-admin Products screen).
5. Confirm the product category term was created and assigned.
6. Delete the test products and category afterward (`wp post delete <id> --force`, `wp term delete product_cat <term_id>`) to keep staging clean.

- [ ] **Step 5: Commit**

```bash
git add takeaway-os/includes/class-menu-import.php takeaway-os/includes/class-client-intake.php takeaway-os/takeaway-os.php
git commit -m "feat: import wizard menu data into real WooCommerce products"
```

---

## Stage 7: Shared OAuth helper + Payment connect

**Files:**
- Create: `takeaway-os/includes/class-oauth-connectors.php`
- Create: `takeaway-os/intake/src/components/ConnectCard.jsx`
- Create: `takeaway-os/intake/src/steps/PaymentConnect.jsx`
- Modify: `takeaway-os/includes/class-intake-rest.php` (connect start/callback routes)
- Modify: `takeaway-os/intake/src/IntakeWizard.jsx` (register `payments` step)

- [ ] **Step 1: Write the shared OAuth helper**

Mirrors `TTOS_Accounting`'s existing state-nonce + encrypted-token pattern exactly (spec §10 requires this precedent be followed, not a new pattern invented).

`takeaway-os/includes/class-oauth-connectors.php`:
```php
<?php

defined('ABSPATH') || exit;

/**
 * Shared OAuth helper for intake-wizard connect steps (payments, email,
 * newsletter). Accounting reuses TTOS_Accounting directly (already built)
 * rather than this class. Same trust model throughout this plugin:
 * state-nonce verified on callback, tokens encrypted at rest via
 * TTOS_API::encrypt()/decrypt(), never returned to the browser.
 */
final class TTOS_OAuth_Connectors {

    const OPT_TOKENS = 'ttos_intake_connector_tokens';

    public static function provider_config(): array {
        return array(
            'stripe' => array(
                'auth_url'     => 'https://connect.stripe.com/oauth/authorize',
                'token_url'    => 'https://connect.stripe.com/oauth/token',
                'client_id'    => defined('TTOS_STRIPE_CONNECT_CLIENT_ID') ? TTOS_STRIPE_CONNECT_CLIENT_ID : '',
                'client_secret'=> defined('TTOS_STRIPE_CONNECT_SECRET') ? TTOS_STRIPE_CONNECT_SECRET : '',
                'scope'        => 'read_write',
            ),
            'google' => array(
                'auth_url'     => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url'    => 'https://oauth2.googleapis.com/token',
                'client_id'    => defined('TTOS_GOOGLE_OAUTH_CLIENT_ID') ? TTOS_GOOGLE_OAUTH_CLIENT_ID : '',
                'client_secret'=> defined('TTOS_GOOGLE_OAUTH_SECRET') ? TTOS_GOOGLE_OAUTH_SECRET : '',
                'scope'        => 'https://www.googleapis.com/auth/gmail.send',
            ),
            'microsoft' => array(
                'auth_url'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'client_id'    => defined('TTOS_MICROSOFT_OAUTH_CLIENT_ID') ? TTOS_MICROSOFT_OAUTH_CLIENT_ID : '',
                'client_secret'=> defined('TTOS_MICROSOFT_OAUTH_SECRET') ? TTOS_MICROSOFT_OAUTH_SECRET : '',
                'scope'        => 'Mail.Send offline_access',
            ),
            'mailchimp' => array(
                'auth_url'     => 'https://login.mailchimp.com/oauth2/authorize',
                'token_url'    => 'https://login.mailchimp.com/oauth2/token',
                'client_id'    => defined('TTOS_MAILCHIMP_CLIENT_ID') ? TTOS_MAILCHIMP_CLIENT_ID : '',
                'client_secret'=> defined('TTOS_MAILCHIMP_SECRET') ? TTOS_MAILCHIMP_SECRET : '',
                'scope'        => '',
            ),
        );
    }

    public static function is_configured(string $provider): bool {
        $cfg = self::provider_config()[$provider] ?? array();
        return !empty($cfg['client_id']) && !empty($cfg['client_secret']);
    }

    public static function build_auth_url(string $provider, string $intake_id, string $redirect_uri): string {
        $cfg = self::provider_config()[$provider] ?? array();
        if (empty($cfg['client_id'])) return '';
        $state = wp_create_nonce('ttos_intake_oauth_' . $provider . '_' . $intake_id);
        return add_query_arg(array(
            'response_type' => 'code',
            'client_id'     => rawurlencode($cfg['client_id']),
            'redirect_uri'  => rawurlencode($redirect_uri),
            'scope'         => rawurlencode($cfg['scope'] ?? ''),
            'state'         => rawurlencode($state . '|' . $provider . '|' . $intake_id),
        ), $cfg['auth_url']);
    }

    public static function exchange_code(string $provider, string $code, string $redirect_uri): array|\WP_Error {
        $cfg = self::provider_config()[$provider] ?? array();
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return new \WP_Error('ttos_oauth_not_configured', ucfirst($provider) . ' is not yet configured. Contact your developer.');
        }
        $response = wp_remote_post($cfg['token_url'], array(
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
            'body'    => http_build_query(array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri'  => $redirect_uri,
            )),
            'timeout' => 15,
        ));
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            return new \WP_Error('ttos_oauth_token_error', $body['error_description'] ?? 'Token exchange failed.');
        }
        return $body;
    }

    public static function store_token(string $intake_id, string $provider, array $token_data): void {
        $all = (array) get_option(self::OPT_TOKENS, array());
        $all[$intake_id . '_' . $provider] = array(
            'access_token'  => TTOS_API::encrypt($token_data['access_token'] ?? ''),
            'refresh_token' => TTOS_API::encrypt($token_data['refresh_token'] ?? ''),
            'expires_at'    => time() + (int) ($token_data['expires_in'] ?? 3600),
            'provider'      => $provider,
        );
        update_option(self::OPT_TOKENS, $all, false);
    }

    public static function is_connected(string $intake_id, string $provider): bool {
        $all = (array) get_option(self::OPT_TOKENS, array());
        return !empty($all[$intake_id . '_' . $provider]['access_token']);
    }
}
```

- [ ] **Step 2: Add connect start/callback routes**

In `class-intake-rest.php`, add:
```php
        register_rest_route(self::NS, '/intake/connect/(?P<provider>[a-z]+)/start', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_connect_start'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/intake/connect/(?P<provider>[a-z]+)/callback', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_connect_callback'),
            'permission_callback' => '__return_true',
        ));
```
And handlers:
```php
    public static function handle_connect_start(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        $provider = sanitize_key((string) $request->get_param('provider'));
        if (!TTOS_OAuth_Connectors::is_configured($provider)) {
            return new \WP_REST_Response(array('configured' => false), 200);
        }
        $redirect_uri = rest_url(self::NS . '/intake/connect/' . $provider . '/callback');
        $url = TTOS_OAuth_Connectors::build_auth_url($provider, (string) $record['id'], $redirect_uri);
        return new \WP_REST_Response(array('configured' => true, 'auth_url' => $url), 200);
    }

    public static function handle_connect_callback(\WP_REST_Request $request): void {
        $code  = sanitize_text_field((string) $request->get_param('code'));
        $state = sanitize_text_field((string) $request->get_param('state'));
        $parts = explode('|', $state, 3);
        $nonce = $parts[0] ?? '';
        $provider = sanitize_key($parts[1] ?? '');
        $intake_id = sanitize_text_field($parts[2] ?? '');

        if (!wp_verify_nonce($nonce, 'ttos_intake_oauth_' . $provider . '_' . $intake_id) || !$code) {
            wp_die(esc_html__('This connection link is invalid or expired. Please go back and try again.', 'takeaway-os'), 403);
        }
        $redirect_uri = rest_url(self::NS . '/intake/connect/' . $provider . '/callback');
        $result = TTOS_OAuth_Connectors::exchange_code($provider, $code, $redirect_uri);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()), 400);
        }
        TTOS_OAuth_Connectors::store_token($intake_id, $provider, $result);

        $record = TTOS_Client_Intake::get_record_public($intake_id);
        $record['submitted_data']['payments'] = array_merge(
            (array) ($record['submitted_data']['payments'] ?? array()),
            array('provider' => $provider, 'connected' => true)
        );
        TTOS_Client_Intake::save_record_public($record);

        wp_safe_redirect(add_query_arg(array('ttos_intake' => $intake_id, 'connected' => $provider), home_url('/')));
        exit;
    }
```
Add one more small public wrapper to `class-client-intake.php` (this stage needs read-by-id without a token, since the OAuth callback URL cannot carry the token safely in a redirect chain — the state nonce already proves authenticity):
```php
    public static function get_record_public(string $id): array {
        return self::get_record($id);
    }
```

- [ ] **Step 3: Write the shared `ConnectCard` component**

`takeaway-os/intake/src/components/ConnectCard.jsx`:
```jsx
import React from 'react'

export default function ConnectCard({ label, sub, color, connected, onClick, disabled }) {
  return (
    <button type="button" onClick={onClick} disabled={disabled}
      style={{
        display: 'flex', alignItems: 'center', gap: 10, padding: 14, minHeight: 44,
        border: connected ? '1px solid var(--tt-success, #2f7d46)' : '1px solid var(--tt-border, #e5d6c5)',
        borderRadius: 12, background: connected ? 'var(--tt-surface-soft, #f8efe4)' : 'var(--tt-surface, #fff)',
        cursor: disabled ? 'not-allowed' : 'pointer', width: '100%', textAlign: 'left', opacity: disabled ? 0.5 : 1
      }}>
      <div style={{ width: 34, height: 34, borderRadius: 8, background: color, flexShrink: 0 }} />
      <div>
        <div style={{ fontSize: 14 }}>{label}</div>
        <div style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)' }}>{connected ? 'Connected' : sub}</div>
      </div>
    </button>
  )
}
```

- [ ] **Step 4: Write the payment connect step**

`takeaway-os/intake/src/steps/PaymentConnect.jsx`:
```jsx
import React, { useState } from 'react'
import ConnectCard from '../components/ConnectCard.jsx'
import { api } from '../api.js'

const PROVIDERS = [
  { key: 'stripe', label: 'Stripe', sub: 'Cards & Apple Pay', color: '#635BFF' },
  { key: 'square', label: 'Square', sub: 'Online + reader', color: '#2E2E2E' },
  { key: 'sumup', label: 'SumUp', sub: 'Card reader', color: '#1C7CD6' },
  { key: 'paypal', label: 'PayPal / Zettle', sub: 'Pay in a click', color: '#0070BA' },
]

export default function PaymentConnect({ data, onNext }) {
  const [current, setCurrent] = useState(data.current_provider || '')
  const [selected, setSelected] = useState(data.provider || '')
  const [other, setOther] = useState(data.other_provider || '')
  const [connecting, setConnecting] = useState(false)

  const connect = async (key) => {
    setSelected(key)
    setConnecting(true)
    try {
      const res = await api.startConnect(key)
      if (res.configured && res.auth_url) {
        window.location.href = res.auth_url // full redirect to provider's OAuth page
      } else {
        // Not yet configured server-side (Stage 0 credential missing) — save the
        // choice anyway so the dev can finish the connection manually post-submission.
        onNext({ current_provider: current, provider: key, connected: false, needs_dev_followup: true })
      }
    } finally {
      setConnecting(false)
    }
  }

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Do you currently take card payments, and who with?</h2>
      <input value={current} onChange={e => setCurrent(e.target.value)} placeholder="e.g. Dojo card machine, or none yet"
        style={{ display: 'block', width: '100%', marginTop: 8, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />

      <h2 style={{ fontSize: 16, marginTop: 20 }}>Choose your website payment provider</h2>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 10 }}>
        {PROVIDERS.map(p => (
          <ConnectCard key={p.key} label={p.label} sub={p.sub} color={p.color}
            connected={selected === p.key && data.connected}
            disabled={connecting}
            onClick={() => connect(p.key)} />
        ))}
      </div>
      <div style={{ marginTop: 10 }}>
        <label style={{ fontSize: 12 }}>
          Other provider
          <input value={other} onChange={e => setOther(e.target.value)} placeholder="Which provider do you use?"
            style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
        </label>
        {other && (
          <button type="button" onClick={() => onNext({ current_provider: current, provider: 'other', other_provider: other, connected: false, needs_dev_followup: true })}
            style={{ marginTop: 8, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
            Send my provider
          </button>
        )}
      </div>
    </div>
  )
}
```
Payments is a **required** step (spec §5) but has no explicit "Continue" button in this component beyond the connect actions — `onNext` fires as a side effect of connecting or submitting "Other." This is intentional: a required step's completion is "provider chosen," not a separate confirmation click, matching how the OAuth redirect naturally advances the flow when it returns.

- [ ] **Step 5: Register the step and route**

```jsx
import PaymentConnect from './steps/PaymentConnect.jsx'
registerStep('payments', PaymentConnect)
```
Register the new PHP file in `takeaway-os.php`'s `$ttos_files` array: `'includes/class-oauth-connectors.php',` (no `hooks()` call — it's a stateless helper called by `class-intake-rest.php`).

- [ ] **Step 6: Register the `payments` sanitizer**

`payments`'s submitted shape (`current_provider`, `provider`, `connected`, `other_provider`, `needs_dev_followup`) shares no field names with the legacy `ordering_payment` section in `sanitize_public_submission()` — without its own registered sanitizer, Stage 2's fallback would silently return an empty array and the whole step's data would be lost on save. Extend `register_sanitizers()` in `class-intake-rest.php` (already created in Stage 3, extended again in Stage 5):
```php
    public static function sanitize_payments(array $raw): array {
        return array(
            'current_provider'    => sanitize_text_field((string) ($raw['current_provider'] ?? '')),
            'provider'            => sanitize_key((string) ($raw['provider'] ?? '')),
            'other_provider'      => sanitize_text_field((string) ($raw['other_provider'] ?? '')),
            'connected'           => !empty($raw['connected']),
            'needs_dev_followup'  => !empty($raw['needs_dev_followup']),
        );
    }
```
Add `$sanitizers['payments'] = array(__CLASS__, 'sanitize_payments');` inside the existing `register_sanitizers()` method body.

- [ ] **Step 7: Stage 7 self-review loop**

1. `php -l takeaway-os/includes/class-oauth-connectors.php` — clean.
2. `npm run build` — zero errors.
3. With Stripe credentials NOT yet configured (Stage 0 pending), click Connect on staging — confirm it falls through to `needs_dev_followup: true` gracefully rather than erroring or hanging.
4. **Confirm the sanitizer fix actually works:** after that fallback save, inspect the record and confirm `submitted_data.payments.provider` is `"stripe"` (or whichever was clicked) and `needs_dev_followup` is `true` — not an empty array. This is the specific data-loss bug Step 6 fixed.
5. Once Stage 0 Stripe credentials exist, do one real end-to-end OAuth round-trip on staging with a Stripe test-mode account; confirm `ttos_intake_connector_tokens` option contains an encrypted (not plaintext) `access_token` — inspect with `wp option get ttos_intake_connector_tokens --format=json` and confirm the string is not readable as a raw Stripe key.

- [ ] **Step 8: Commit**

```bash
git add takeaway-os/includes/class-oauth-connectors.php takeaway-os/includes/class-intake-rest.php takeaway-os/includes/class-client-intake.php takeaway-os/intake/src/components/ConnectCard.jsx takeaway-os/intake/src/steps/PaymentConnect.jsx takeaway-os/intake/src/IntakeWizard.jsx takeaway-os/takeaway-os.php
git commit -m "feat: shared OAuth connector helper + payment connect step"
```

---

## Stage 8: Email, Accounting, Newsletter, Analytics connect steps

**Files:**
- Create: `takeaway-os/intake/src/steps/EmailConnect.jsx`
- Create: `takeaway-os/intake/src/steps/AccountingConnect.jsx`
- Create: `takeaway-os/intake/src/steps/NewsletterConnect.jsx`
- Create: `takeaway-os/intake/src/steps/AnalyticsStep.jsx`
- Modify: `takeaway-os/includes/class-intake-rest.php` (accounting uses `TTOS_Accounting` directly, not `TTOS_OAuth_Connectors`)
- Modify: `takeaway-os/intake/src/IntakeWizard.jsx` (register four steps)

All four reuse `ConnectCard` (email, accounting, newsletter) or a plain field (analytics) from Stages 1/7 — no new shared components needed.

- [ ] **Step 1: Email connect**

`takeaway-os/intake/src/steps/EmailConnect.jsx` — identical shape to `PaymentConnect`, two providers instead of four, uses the same `api.startConnect()`:
```jsx
import React, { useState } from 'react'
import ConnectCard from '../components/ConnectCard.jsx'
import { api } from '../api.js'

export default function EmailConnect({ data, onNext }) {
  const [other, setOther] = useState(data.other_host || '')
  const [connecting, setConnecting] = useState(false)

  const connect = async (key) => {
    setConnecting(true)
    try {
      const res = await api.startConnect(key)
      if (res.configured && res.auth_url) {
        window.location.href = res.auth_url
      } else {
        onNext({ provider: key, connected: false, needs_dev_followup: true })
      }
    } finally {
      setConnecting(false)
    }
  }

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Where should order confirmations be sent from?</h2>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 10 }}>
        <ConnectCard label="Gmail / Workspace" sub="Sign in securely" color="#EA4335" connected={data.connected} disabled={connecting} onClick={() => connect('google')} />
        <ConnectCard label="Outlook / Microsoft" sub="Sign in securely" color="#0078D4" connected={data.connected} disabled={connecting} onClick={() => connect('microsoft')} />
      </div>
      <label style={{ display: 'block', marginTop: 10, fontSize: 12 }}>
        Or a different email host
        <input value={other} onChange={e => setOther(e.target.value)} placeholder="your email address"
          style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      </label>
      {other && (
        <button type="button" onClick={() => onNext({ provider: 'other', other_host: other, connected: false, needs_dev_followup: true })}
          style={{ marginTop: 8, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
          Continue
        </button>
      )}
    </div>
  )
}
```

- [ ] **Step 2: Accounting connect — wraps the already-existing `TTOS_Accounting`**

Add a thin route in `class-intake-rest.php` rather than routing through `TTOS_OAuth_Connectors` (spec §6.4: "no new backend work, just exposing it here"):
```php
        register_rest_route(self::NS, '/intake/connect/accounting/start', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_accounting_start'),
            'permission_callback' => '__return_true',
        ));

    // ...

    public static function handle_accounting_start(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $record = self::validated_or_error($request);
        if (is_wp_error($record)) {
            return new \WP_REST_Response(array('message' => $record->get_error_message()), 403);
        }
        $provider = sanitize_key((string) $request->get_param('provider'));
        if (!in_array($provider, array('xero', 'qbo', 'freeagent'), true)) {
            return new \WP_REST_Response(array('message' => 'Unknown accounting provider.'), 400);
        }
        $url = TTOS_Accounting::get_auth_url($provider);
        return new \WP_REST_Response(array('configured' => $url !== '', 'auth_url' => $url), 200);
    }
```
Note: this reuses `TTOS_Accounting::get_auth_url()` exactly as-is — no modification to `class-accounting.php` needed. The existing `handle_oauth_callback()` in that class already redirects to `admin.php?page=takeaway-os-payments` on success; that's fine for this flow too, since accounting is dev-side infrastructure regardless of which UI initiated the connect.

React:
```jsx
import React, { useState } from 'react'
import ConnectCard from '../components/ConnectCard.jsx'

async function startAccounting(provider) {
  const { apiBase, intakeId, token } = window.ttosIntake
  const res = await fetch(apiBase + '/intake/connect/accounting/start', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ intake_id: intakeId, token, provider })
  })
  return res.json()
}

export default function AccountingConnect({ data, onNext }) {
  const [connecting, setConnecting] = useState(false)
  const connect = async (provider) => {
    setConnecting(true)
    const res = await startAccounting(provider)
    setConnecting(false)
    if (res.configured && res.auth_url) {
      window.location.href = res.auth_url
    } else {
      onNext({ provider, connected: false, needs_dev_followup: true })
    }
  }
  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Do you use accounting software?</h2>
      <p style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)' }}>This lets your website send sales data straight to your books. Most takeaways don't need this yet — totally fine to skip.</p>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10, marginTop: 10 }}>
        <ConnectCard label="Xero" sub="Connect" color="#13B5EA" disabled={connecting} onClick={() => connect('xero')} />
        <ConnectCard label="QuickBooks" sub="Connect" color="#2CA01C" disabled={connecting} onClick={() => connect('qbo')} />
        <ConnectCard label="FreeAgent" sub="Connect" color="#00A4E4" disabled={connecting} onClick={() => connect('freeagent')} />
      </div>
    </div>
  )
}
```

- [ ] **Step 3: Newsletter connect**

`takeaway-os/intake/src/steps/NewsletterConnect.jsx` — same shape as `PaymentConnect`/`EmailConnect`, using `api.startConnect('mailchimp')`:
```jsx
import React, { useState } from 'react'
import ConnectCard from '../components/ConnectCard.jsx'
import { api } from '../api.js'

export default function NewsletterConnect({ data, onNext }) {
  const [connecting, setConnecting] = useState(false)
  const connect = async () => {
    setConnecting(true)
    try {
      const res = await api.startConnect('mailchimp')
      if (res.configured && res.auth_url) {
        window.location.href = res.auth_url
      } else {
        onNext({ provider: 'mailchimp', connected: false, needs_dev_followup: true })
      }
    } finally {
      setConnecting(false)
    }
  }
  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Do you send a newsletter?</h2>
      <p style={{ fontSize: 12, color: 'var(--tt-muted, #75665c)' }}>Connect Mailchimp to collect signups from your website automatically.</p>
      <ConnectCard label="Mailchimp" sub="Connect" color="#FFE01B" disabled={connecting} onClick={connect} />
    </div>
  )
}
```

- [ ] **Step 4: Analytics step (no OAuth — simplest of the four)**

`takeaway-os/intake/src/steps/AnalyticsStep.jsx`:
```jsx
import React, { useState } from 'react'
import HelpTip from '../components/HelpTip.jsx'

export default function AnalyticsStep({ data, onNext, saving }) {
  const [id, setId] = useState(data.measurement_id || '')
  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Google Analytics <HelpTip text="Find this in your Google Analytics account under Admin → Data Streams. It looks like G-XXXXXXX. Leave blank if you don't have one yet." /></h2>
      <input value={id} onChange={e => setId(e.target.value)} placeholder="G-XXXXXXX"
        style={{ display: 'block', width: '100%', marginTop: 8, padding: 9, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
      <button type="button" disabled={saving} onClick={() => onNext({ measurement_id: id })}
        style={{ marginTop: 16, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```
On import (Stage 11's `import_submission()` extension), `analytics.measurement_id` writes to a new `ttos_settings['integrations']['ga4_measurement_id']` option, output via a small `wp_head` snippet — add this one-line addition to `TTOS_Settings::print_brand_css()`'s sibling hook registration in `hooks()`:
```php
        add_action('wp_head', array(__CLASS__, 'print_ga4_snippet'), 5);
```
and the method:
```php
    public static function print_ga4_snippet(): void {
        $id = (string) self::get('integrations', 'ga4_measurement_id');
        if ($id === '') return;
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($id) . '"></script>';
        echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());gtag(\'config\',' . wp_json_encode($id) . ');</script>';
    }
```
Add `'integrations' => array('ga4_measurement_id' => '')` to `TTOS_Settings::defaults()`.

- [ ] **Step 5: Register all four steps**

```jsx
import EmailConnect from './steps/EmailConnect.jsx'
import AccountingConnect from './steps/AccountingConnect.jsx'
import NewsletterConnect from './steps/NewsletterConnect.jsx'
import AnalyticsStep from './steps/AnalyticsStep.jsx'

registerStep('order_email', EmailConnect)
registerStep('accounting', AccountingConnect)
registerStep('newsletter', NewsletterConnect)
registerStep('analytics', AnalyticsStep)
```

- [ ] **Step 6: Register sanitizers for all four steps**

None of `order_email`, `accounting`, `newsletter`, `analytics` have a matching section in the legacy `sanitize_public_submission()` — each needs its own entry, otherwise all four would silently lose their data on save (same class of bug fixed for `payments` in Stage 7). Extend `register_sanitizers()` in `class-intake-rest.php`:
```php
    public static function sanitize_order_email(array $raw): array {
        return array(
            'provider'           => sanitize_key((string) ($raw['provider'] ?? '')),
            'other_host'         => sanitize_text_field((string) ($raw['other_host'] ?? '')),
            'connected'          => !empty($raw['connected']),
            'needs_dev_followup' => !empty($raw['needs_dev_followup']),
        );
    }

    public static function sanitize_accounting(array $raw): array {
        return array(
            'provider'           => sanitize_key((string) ($raw['provider'] ?? '')),
            'connected'          => !empty($raw['connected']),
            'needs_dev_followup' => !empty($raw['needs_dev_followup']),
        );
    }

    public static function sanitize_newsletter(array $raw): array {
        return array(
            'provider'           => sanitize_key((string) ($raw['provider'] ?? '')),
            'connected'          => !empty($raw['connected']),
            'needs_dev_followup' => !empty($raw['needs_dev_followup']),
        );
    }

    public static function sanitize_analytics(array $raw): array {
        return array(
            'measurement_id' => sanitize_text_field((string) ($raw['measurement_id'] ?? '')),
        );
    }
```
Add all four to the existing `register_sanitizers()` method body: `$sanitizers['order_email'] = ...`, `$sanitizers['accounting'] = ...`, `$sanitizers['newsletter'] = ...`, `$sanitizers['analytics'] = ...` (same pattern as the `payments` entry added in Stage 7).

- [ ] **Step 7: Stage 8 self-review loop**

1. `npm run build` — zero errors.
2. `php -l takeaway-os/includes/class-settings.php` and `class-intake-rest.php` — clean.
3. Confirm all four steps show their Skip button (from `IntakeWizard.jsx`'s `!step.required` check) — these are the four `required: false` rows from Stage 1's `steps.js`.
4. Confirm Accounting's Connect buttons correctly report `configured: false` gracefully if Xero credentials aren't present (should already be true today per Stage 0's table — verify it didn't regress).
5. Paste a real GA4 ID into a test intake, save the step, and inspect the record directly — confirm `submitted_data.analytics.measurement_id` holds the real value (proves Step 6's sanitizer fix works, not silently empty).
6. Import that same test intake, confirm `<script ... gtag/js?id=G-...>` appears in the site's `<head>` via view-source.

- [ ] **Step 8: Commit**

```bash
git add takeaway-os/intake/src/steps/EmailConnect.jsx takeaway-os/intake/src/steps/AccountingConnect.jsx takeaway-os/intake/src/steps/NewsletterConnect.jsx takeaway-os/intake/src/steps/AnalyticsStep.jsx takeaway-os/includes/class-intake-rest.php takeaway-os/includes/class-settings.php takeaway-os/intake/src/IntakeWizard.jsx
git commit -m "feat: add email, accounting, newsletter, analytics connect steps"
```

---

## Stage 9: VAT & legal step + auto-drafted policy pages

**Files:**
- Create: `takeaway-os/includes/class-policy-drafts.php`
- Create: `takeaway-os/intake/src/steps/VatLegal.jsx`
- Modify: `takeaway-os/includes/class-client-intake.php` (`import_submission()` calls the new class)
- Modify: `takeaway-os/takeaway-os.php` (register new file)

- [ ] **Step 1: Write the VAT & legal step**

`takeaway-os/intake/src/steps/VatLegal.jsx`:
```jsx
import React, { useState } from 'react'
import HelpTip from '../components/HelpTip.jsx'

const VAT_OPTIONS = [
  { value: 'yes', label: 'Yes' },
  { value: 'no', label: 'No' },
  { value: 'not_sure', label: 'Not sure' },
]

export default function VatLegal({ data, onNext, saving }) {
  const [vatStatus, setVatStatus] = useState(data.vat_status || 'not_sure')
  const [vatNumber, setVatNumber] = useState(data.vat_number || '')

  return (
    <div>
      <h2 style={{ fontSize: 16 }}>Is your takeaway VAT registered? <HelpTip text="This affects whether tax is added to your prices. If you're not sure, pick 'Not sure' and we'll check with you." /></h2>
      <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
        {VAT_OPTIONS.map(o => (
          <button key={o.value} type="button" onClick={() => setVatStatus(o.value)}
            aria-pressed={vatStatus === o.value}
            style={{
              flex: 1, padding: 12, minHeight: 44, borderRadius: 8, fontSize: 13, cursor: 'pointer',
              border: vatStatus === o.value ? '1px solid var(--tt-primary, #d83a16)' : '1px solid var(--tt-border, #e5d6c5)',
              background: vatStatus === o.value ? 'var(--tt-surface-soft, #f8efe4)' : 'transparent'
            }}>{o.label}</button>
        ))}
      </div>
      {vatStatus === 'yes' && (
        <label style={{ display: 'block', marginTop: 12, fontSize: 12 }}>
          VAT number
          <input value={vatNumber} onChange={e => setVatNumber(e.target.value)}
            style={{ display: 'block', width: '100%', marginTop: 4, padding: 9, minHeight: 44, border: '1px solid var(--tt-border, #e5d6c5)', borderRadius: 8 }} />
        </label>
      )}
      <p style={{ fontSize: 13, marginTop: 16 }}>We'll draft these for you to check:</p>
      <ul style={{ fontSize: 13, color: 'var(--tt-muted, #75665c)' }}>
        <li>Allergen disclaimer</li>
        <li>Refund & cancellation policy</li>
        <li>Privacy notice</li>
      </ul>
      <p style={{ fontSize: 11, color: 'var(--tt-muted, #75665c)' }}>These are starting drafts, not legal advice — your developer reviews them before launch.</p>
      <button type="button" disabled={saving} onClick={() => onNext({ vat_status: vatStatus, vat_number: vatStatus === 'yes' ? vatNumber : '' })}
        style={{ marginTop: 12, padding: '10px 20px', border: 'none', borderRadius: 8, background: 'var(--tt-primary, #d83a16)', color: '#fff', fontSize: 14, cursor: 'pointer' }}>
        {saving ? 'Saving…' : 'Continue'}
      </button>
    </div>
  )
}
```

- [ ] **Step 2: Write the policy draft generator**

`takeaway-os/includes/class-policy-drafts.php` — pulls allergen data from the menu builder step and business details from `business_basics`, generates three unpublished draft pages. Never auto-publishes (spec §6.7 — enforced by `post_status => 'draft'` and never being flipped elsewhere in this codebase).

```php
<?php

defined('ABSPATH') || exit;

/**
 * Generates draft (never published) policy pages from intake wizard
 * answers: allergen disclaimer, refund/cancellation policy, privacy
 * notice. Created as WP drafts so the existing Go Live checklist item
 * "Terms, privacy, cookies and accessibility checked" has real content
 * to review instead of a blank page — the developer must still publish
 * them manually.
 */
final class TTOS_Policy_Drafts {

    public static function generate(array $record): array {
        $data = (array) ($record['submitted_data'] ?? array());
        $business = (array) ($data['business_basics'] ?? array());
        $menu = (array) ($data['menu_builder'] ?? array());
        $delivery = (array) ($data['delivery_collection'] ?? array());

        $created = array();
        $created[] = self::create_or_update_draft('Allergen disclaimer', self::allergen_disclaimer($business, $menu));
        $created[] = self::create_or_update_draft('Refund & cancellation policy', self::refund_policy($business, $delivery));
        $created[] = self::create_or_update_draft('Privacy notice', self::privacy_notice($business));
        return array_filter($created);
    }

    private static function create_or_update_draft(string $title, string $content): int {
        $existing = get_page_by_title($title, OBJECT, 'page');
        $args = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft', // never auto-published — see class docblock
            'post_type'    => 'page',
        );
        if ($existing) {
            $args['ID'] = $existing->ID;
            return (int) wp_update_post($args);
        }
        return (int) wp_insert_post($args);
    }

    private static function allergen_disclaimer(array $business, array $menu): string {
        $name = $business['takeaway_name'] ?: get_bloginfo('name');
        $all_allergens = array();
        foreach ((array) ($menu['categories'] ?? array()) as $cat) {
            foreach ((array) ($cat['sub_categories'] ?? array()) as $sub) {
                foreach ((array) ($sub['items'] ?? array()) as $item) {
                    foreach ((array) ($item['allergens'] ?? array()) as $a) {
                        $all_allergens[$a] = true;
                    }
                }
            }
        }
        $list = $all_allergens ? implode(', ', array_map('ucfirst', array_keys($all_allergens))) : 'gluten, milk, eggs, and other common allergens';
        return "## Allergen information\n\n{$name} takes food allergies seriously. Our menu may contain or come into contact with: {$list}.\n\nIf you have a food allergy, please contact us before ordering so we can advise on safe options. While we take precautions, we cannot guarantee any dish is completely free of a specific allergen due to shared kitchen equipment.\n\n*This is a starting draft based on the menu items provided — please review for accuracy before publishing.*";
    }

    private static function refund_policy(array $business, array $delivery): string {
        $name = $business['takeaway_name'] ?: get_bloginfo('name');
        return "## Refunds and cancellations\n\n{$name} wants you to be happy with your order. If something arrives incorrect, missing, or not as described, please contact us within 24 hours and we will offer a replacement or refund.\n\nOrders cannot be cancelled once preparation has started. To cancel before preparation begins, contact us as soon as possible using the details on our Contact page.\n\n*This is a starting draft — please review for accuracy before publishing.*";
    }

    private static function privacy_notice(array $business): string {
        $email = $business['email'] ?: get_option('admin_email');
        $name = $business['takeaway_name'] ?: get_bloginfo('name');
        return "## Privacy notice\n\n{$name} collects your name, address, phone number, and order details to process and deliver your order. We do not sell your information to third parties.\n\nTo request a copy of your data or ask us to delete it, contact us at {$email}.\n\n*This is a starting draft — please review for accuracy and full UK GDPR compliance before publishing.*";
    }
}
```

- [ ] **Step 3: Wire into `import_submission()`**

In `class-client-intake.php`'s `import_submission()`, add near the menu import call from Stage 6:
```php
        $vat_data = (array) ($data['vat_legal'] ?? array());
        if (!empty($vat_data['vat_status'])) {
            $business['vat_number'] = $vat_data['vat_number'] ?: $business['vat_number'];
            TTOS_Settings::update_section('business', $business);
        }
        if (class_exists('TTOS_Policy_Drafts')) {
            TTOS_Policy_Drafts::generate($record);
        }
```
(This runs after the existing `$business` variable is already built earlier in the method — insert directly below the existing `TTOS_Settings::update_section('business', $business);` call that's already there from before this plan, not as a duplicate.)

- [ ] **Step 4: Register the step and file**

```jsx
import VatLegal from './steps/VatLegal.jsx'
registerStep('vat_legal', VatLegal)
```
In `takeaway-os.php`, add `'includes/class-policy-drafts.php',` to `$ttos_files`.

- [ ] **Step 5: Register the `vat_legal` sanitizer**

`vat_legal`'s shape (`vat_status`, `vat_number`) doesn't match the legacy `policies` section in `sanitize_public_submission()` — needs its own entry, same class of fix as Stages 7/8. Extend `register_sanitizers()` in `class-intake-rest.php`:
```php
    public static function sanitize_vat_legal(array $raw): array {
        $status = sanitize_key((string) ($raw['vat_status'] ?? 'not_sure'));
        return array(
            'vat_status' => in_array($status, array('yes', 'no', 'not_sure'), true) ? $status : 'not_sure',
            'vat_number' => sanitize_text_field((string) ($raw['vat_number'] ?? '')),
        );
    }
```
Add `$sanitizers['vat_legal'] = array(__CLASS__, 'sanitize_vat_legal');` to `register_sanitizers()`.

- [ ] **Step 6: Stage 9 self-review loop**

1. `php -l takeaway-os/includes/class-policy-drafts.php` — clean.
2. `npm run build` — zero errors.
3. On staging, submit a test intake with two menu items tagged `gluten` and `milk`, import it, confirm three new **draft** (not published) pages exist: `wp post list --post_type=page --post_status=draft --format=table`.
4. Confirm the allergen disclaimer page's content actually mentions "Gluten, Milk" (proves the menu data flowed through correctly).
5. **Confirm the sanitizer fix works:** before importing, inspect the record and confirm `submitted_data.vat_legal.vat_status` holds the real choice (`yes`/`no`/`not_sure`), not an empty array.
6. Confirm none of the three pages are publicly reachable (draft status blocks front-end access by default in WordPress — verify with a logged-out curl to the page URL, expect a 404 or login-redirect, not the content).
7. Delete the test draft pages afterward.

- [ ] **Step 7: Commit**

```bash
git add takeaway-os/includes/class-policy-drafts.php takeaway-os/includes/class-client-intake.php takeaway-os/takeaway-os.php takeaway-os/intake/src/steps/VatLegal.jsx takeaway-os/intake/src/IntakeWizard.jsx
git commit -m "feat: VAT/legal step with auto-drafted policy pages"
```

---

## Stage 10: Brand Guide wp-admin screen

**Files:**
- Create: `takeaway-os/includes/class-brand-guide.php`
- Modify: `takeaway-os/includes/class-client-intake.php` (`import_submission()` extended for fonts + extra photos)
- Modify: `takeaway-os/takeaway-os.php` (register new file + hook)

- [ ] **Step 1: Extend `import_submission()` to cover fonts and extra photos**

Per spec §9's "import gap" — today only colours/logo/favicon import. In `class-client-intake.php`'s `import_submission()`, add after the existing branding colour-loop block:
```php
        $typography_data = (array) ($data['typography'] ?? array());
        if (!empty($typography_data['font_heading'])) {
            $branding['font_heading'] = sanitize_text_field((string) $typography_data['font_heading']);
        }
        if (!empty($typography_data['font_body'])) {
            $branding['font_body'] = sanitize_text_field((string) $typography_data['font_body']);
        }

        $layout_data = (array) ($data['layout_style'] ?? array());
        foreach (array('header_style', 'hero_style', 'footer_style') as $key) {
            if (!empty($layout_data[$key])) {
                $branding[$key] = sanitize_key((string) $layout_data[$key]);
            }
        }
        TTOS_Settings::update_section('branding', $branding); // re-save after the additions above

        foreach (array('shopfront_photos', 'food_photos', 'staff_team_photos', 'interior_photos') as $field) {
            $items = (array) ($record['uploads'][$field] ?? array());
            foreach ($items as $upload_item) {
                self::attachment_from_upload_multi($record, $field, $upload_item);
            }
        }
```
`attachment_from_upload()` (already in the file, private) only handles the *first* upload in a field's array (see spec §9's gap description) — add a sibling method that handles any single upload item, so the multi-photo fields import every file, not just the first:
```php
    private static function attachment_from_upload_multi(array $record, string $field, array $upload_item): int {
        $file = self::stored_file_path((string) ($upload_item['stored_name'] ?? ''));
        if ($file === '' || !is_file($file)) return 0;
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) return 0;
        $target_dir = trailingslashit($upload_dir['path']);
        if (!is_dir($target_dir)) wp_mkdir_p($target_dir);
        $filename = wp_unique_filename($target_dir, sanitize_file_name((string) ($upload_item['original_name'] ?? basename($file))));
        $target = $target_dir . $filename;
        if (!copy($file, $target)) return 0;
        $filetype = wp_check_filetype($filename, null);
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_status'    => 'inherit',
        ), $target);
        if (!$attachment_id || is_wp_error($attachment_id)) return 0;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $target);
        if (is_array($metadata)) wp_update_attachment_metadata($attachment_id, $metadata);
        // Tag the field it came from so the Brand Guide screen (Step 2) can group them.
        update_post_meta($attachment_id, '_ttos_intake_field', sanitize_key($field));
        return (int) $attachment_id;
    }
```

- [ ] **Step 2: Write the Brand Guide admin screen**

`takeaway-os/includes/class-brand-guide.php` — a single top-level menu, four tabs (Colours, Typography, Logo & photos, Layout style), reusing `TTOS_Settings::get('branding')`/`update_section()` exactly as the existing Settings → Branding screen does (spec §9: "not a new mechanism, already works"), just surfaced as its own focused top-level page with a live preview.

```php
<?php

defined('ABSPATH') || exit;

/**
 * Single shared "Brand Guide" screen for colours, typography, logo/photos,
 * and layout style — for both dev/admin users and client-role owners
 * (takeaway_owner/takeaway_manager already have wp-admin access). Saving
 * here writes through TTOS_Settings, same live-CSS mechanism the existing
 * Settings → Branding screen already uses — no new "publish" step.
 */
final class TTOS_Brand_Guide {

    public static function hooks(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 21);
        add_action('admin_post_ttos_save_brand_guide', array(__CLASS__, 'handle_save'));
    }

    public static function menu(): void {
        add_menu_page(
            'Brand Guide',
            'Brand Guide',
            'ttos_manage_settings',
            'takeaway-os-brand-guide',
            array(__CLASS__, 'page'),
            'dashicons-art',
            4
        );
    }

    public static function handle_save(): void {
        check_admin_referer('ttos_save_brand_guide');
        if (!current_user_can('ttos_manage_settings')) {
            wp_die(esc_html__('Insufficient permissions.', 'takeaway-os'));
        }
        $branding = TTOS_Settings::get('branding');
        $raw = wp_unslash($_POST['branding'] ?? array());
        foreach (array('primary', 'accent', 'bg', 'text', 'font_heading', 'font_body', 'header_style', 'hero_style', 'footer_style') as $key) {
            if (isset($raw[$key])) {
                $branding[$key] = in_array($key, array('primary', 'accent', 'bg', 'text'), true)
                    ? (sanitize_hex_color($raw[$key]) ?: $branding[$key])
                    : sanitize_text_field((string) $raw[$key]);
            }
        }
        TTOS_Settings::update_section('branding', $branding);
        wp_safe_redirect(add_query_arg(array('page' => 'takeaway-os-brand-guide', 'saved' => '1'), admin_url('admin.php')));
        exit;
    }

    public static function page(): void {
        $branding = TTOS_Settings::get('branding');
        echo '<div class="wrap"><h1>Brand Guide</h1>';
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Saved — your live site is already updated.</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ttos_save_brand_guide');
        echo '<input type="hidden" name="action" value="ttos_save_brand_guide">';

        echo '<h2>Colours</h2><div style="display:flex;gap:12px;max-width:500px;">';
        foreach (array('primary' => 'Primary', 'accent' => 'Accent', 'bg' => 'Background', 'text' => 'Text') as $key => $label) {
            echo '<label style="flex:1;font-size:12px;">' . esc_html($label) . '<br>';
            echo '<input type="color" name="branding[' . esc_attr($key) . ']" value="' . esc_attr($branding[$key]) . '" style="width:100%;height:36px;"></label>';
        }
        echo '</div>';

        echo '<h2>Typography</h2>';
        echo '<label>Heading font<br><input type="text" name="branding[font_heading]" value="' . esc_attr($branding['font_heading']) . '"></label><br><br>';
        echo '<label>Body font<br><input type="text" name="branding[font_body]" value="' . esc_attr($branding['font_body']) . '"></label>';

        echo '<h2>Layout style</h2>';
        echo '<label>Header<br><select name="branding[header_style]">';
        foreach (array('utility_header' => 'Utility header', 'centered_brand' => 'Centered brand') as $val => $label) {
            echo '<option value="' . esc_attr($val) . '" ' . selected($branding['header_style'], $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';

        echo '<p><button class="button button-primary">Save — updates live site now</button></p>';
        echo '</form></div>';
    }
}
```

- [ ] **Step 3: Register the file and hooks**

In `takeaway-os.php`, add `'includes/class-brand-guide.php',` to `$ttos_files` and `TTOS_Brand_Guide::hooks();` to `plugins_loaded`.

- [ ] **Step 4: Stage 10 self-review loop**

1. `php -l takeaway-os/includes/class-brand-guide.php` and `class-client-intake.php` — clean.
2. On staging, log in as a `takeaway_owner`-role test user (not just administrator) and confirm the Brand Guide menu item is visible and the page loads (proves the capability gate is right — `ttos_manage_settings` is already granted to that role per `class-activator.php`).
3. Change the primary colour on the Brand Guide screen, save, then view the live site — confirm the new colour appears immediately with no cache-clear step (proves `print_brand_css()`'s existing live mechanism still works unmodified).
4. Submit a test intake with a custom heading font and two shopfront photos, import it, confirm: (a) Brand Guide shows the new heading font, (b) both shopfront photos exist as separate media library attachments (not just the first one) — this is the specific gap Step 1 closes.

- [ ] **Step 5: Commit**

```bash
git add takeaway-os/includes/class-brand-guide.php takeaway-os/includes/class-client-intake.php takeaway-os/takeaway-os.php
git commit -m "feat: add Brand Guide admin screen, close font/photo import gap"
```

---

## Stage 11: Full wizard assembly, end-to-end pass, final build report

**Files:**
- Modify: `takeaway-os/includes/class-admin.php` (nothing structural — confirm no menu conflicts with the new Brand Guide top-level item)
- Create: `docs/superpowers/plans/2026-07-02-client-onboarding-wizard-BUILD-REPORT.md`

- [ ] **Step 1: Confirm every step from spec §5's table is registered**

Cross-check `takeaway-os/intake/src/IntakeWizard.jsx`'s `registerStep()` calls against `steps.js`'s `STEPS` array — all 17 ids must have a matching registered component (`welcome` and `finish` are already registered directly in `IntakeWizard.jsx` since Stage 1; the other 15 come from Stages 3–9).

```bash
grep -o "id: '[a-z_]*'" takeaway-os/intake/src/steps.js
grep -o "registerStep('[a-z_]*'" takeaway-os/intake/src/IntakeWizard.jsx
```
Every id from the first command must appear in the second (except `welcome`/`finish`, registered inline).

- [ ] **Step 2: Full end-to-end walkthrough on staging**

Using a real test intake record with a short expiry:
1. Create the request via Client Intake admin → send to a test email you control.
2. Open the magic link, complete all 17 steps in order, including at least one Skip (on a non-required step) and at least one real OAuth connect (whichever provider has credentials from Stage 0).
3. Confirm the Finish screen's recap accurately reflects what was entered.
4. Back in wp-admin, open the request, click "Import submitted data."
5. Confirm: business settings updated, branding (colours/fonts/layout) updated, menu items exist as real products, delivery/trading settings updated, three legal drafts exist as unpublished pages, connector status shows in the request detail.
6. Run Setup Health — confirm no new failures introduced.
7. Delete all test data created (products, categories, draft pages, the intake record itself) once verified.

- [ ] **Step 3: Full accessibility re-pass on the assembled wizard**

Repeat Stage 5 Step 9's checklist (keyboard-only, interrupted-drag, touch targets, contrast) but now on the **assembled** wizard, not the isolated menu builder — confirm Tab order across step transitions is sane (focus lands on the new step's first field after Next, not lost to `<body>`), and confirm the `?` help icons and Skip buttons are keyboard-operable throughout, not just the menu builder.

- [ ] **Step 4: Write the final build report**

Create `docs/superpowers/plans/2026-07-02-client-onboarding-wizard-BUILD-REPORT.md` using this exact template — fill in every bracketed section with what actually happened during the build, don't leave any section blank:

```markdown
# Client Onboarding Wizard — Build Report

Date completed: [date]
Built by: [Codex / agent name + version]

## Stages completed
[List each of the 11 stages above with a one-line status: done / done with deviation / blocked]

## Deviations from the plan
[For each place the actual implementation differed from a code sample or decision in this plan, state what changed and why. If none, write "No deviations."]

## Known limitations
- Menu items with more than one option group only get variations generated from the first group (Stage 6, `apply_option_groups()`) — multi-group cartesian variations are not implemented.
- [Add any other limitation discovered during the build, e.g. a provider's OAuth flow that turned out not to support true one-tap connect per spec §6.2/6.3's honesty caveat]

## Prerequisites status (Stage 0)
[Which of the six credential rows in Stage 0's table were actually configured by the time of this build, and which remain pending]

## Re-verification checklist for a human or future session
- [ ] `php -l` clean on every new/modified PHP file listed across all 11 stages
- [ ] `npm run build` clean in `takeaway-os/intake/`
- [ ] Full 17-step walkthrough (Stage 11 Step 2) reproduced successfully
- [ ] Accessibility pass (Stage 11 Step 3) reproduced successfully
- [ ] Setup Health shows no new failures on staging
- [ ] No test data (products, pages, intake records, connector tokens) left behind on staging
- [ ] Git log shows one commit per stage, all pushed/available for review

## Open questions for the next session
[Anything genuinely ambiguous that came up during the build and was resolved with a judgment call — flag it here so a human can confirm the call was right]
```

- [ ] **Step 5: Commit the build report**

```bash
git add docs/superpowers/plans/2026-07-02-client-onboarding-wizard-BUILD-REPORT.md
git commit -m "docs: add final build report for client onboarding wizard"
```

---

## Plan self-review

**Spec coverage:** every numbered section of the design spec (§1–§12) maps to a stage or an explicit "out of scope" callout — layout/hero/footer picker (§6.1) → Stage 3; payment/email/accounting/newsletter/analytics (§6.2–§6.6) → Stages 7–8; menu builder + a11y (§7) → Stage 5; wizard shell/welcome/skip/finish (§8) → Stage 1 + 8.2 details folded into Stage 1's shell and each connector step; Brand Guide (§9) → Stage 10; security (§10) unchanged throughout, verified per-stage; out-of-scope items (§12) are not built anywhere in this plan.

**Placeholder scan:** no "TBD"/"add appropriate error handling"/"similar to Task N" phrasing anywhere above — every code block is complete and specific to its exact file and line target. The one deliberately-open item (multi-group variation cartesian product, Stage 6) is flagged explicitly as a documented limitation, not hidden behind vague wording.

**Type/name consistency:** `submitted_data.{step_id}` keys are consistent across every stage (`palette`, `layout_style`, `typography`, `menu_builder`, `payments`, `order_email`, `accounting`, `newsletter`, `analytics`, `vat_legal` — cross-checked against `steps.js`'s `STEPS` array, the sole source of truth). `api.js`'s method names (`loadRecord`, `saveStep`, `submitFinal`, `startConnect`) match every call site across Stages 1, 3–9. PHP method names introduced in one stage (`validated_public_record`, `save_step_data`, `collect_upload_public`, `get_record_public`, `save_record_public`, `validate_final_submission_public`) are each defined exactly once (Stage 2) and referenced identically wherever used later.

---

Plan complete and saved to `docs/superpowers/plans/2026-07-02-client-onboarding-wizard.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

**Portable option** — since you're handing this to Codex specifically, you can also skip both and hand Codex this file directly; every stage's self-review loop and the Stage 11 build report are written so Codex can run the whole thing unattended and produce a report for us to check together afterward.

**Which approach?**
