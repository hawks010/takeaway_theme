import React from 'react'

function safeCount(items) {
  return Array.isArray(items) ? items.length : 0
}

export default function Finish({ draft, onToggle }) {
  const menuCategories = safeCount(draft.menu_content?.categories_tree)
  const skipped = Object.keys(draft.wizard_meta?.skips || {}).length
  const measurementId = draft.analytics?.measurement_id

  return (
    <section className="ttos-intake-step">
      <p className="ttos-intake-step-eyebrow">Finish</p>
      <h2>Final review</h2>
      <p>Before we send this back to the developer dashboard, please confirm the essentials below.</p>

      <div className="ttos-intake-summary-grid">
        <article>
          <strong>Brand choices captured</strong>
          <span>Colours, layouts, fonts and media are ready for the Brand Guide.</span>
        </article>
        <article>
          <strong>{menuCategories} menu categories</strong>
          <span>Structured menu data will be available for review and import.</span>
        </article>
        <article>
          <strong>{skipped} optional steps skipped</strong>
          <span>Any skip notes stay with the request for the developer to review.</span>
        </article>
        <article>
          <strong>{measurementId || 'No GA ID yet'}</strong>
          <span>Analytics can be wired later if you are not ready with a Measurement ID yet.</span>
        </article>
      </div>

      <div className="ttos-intake-check-list">
        <label>
          <input
            type="checkbox"
            checked={draft.final_confirmation?.details_accurate === '1'}
            onChange={(event) => onToggle('details_accurate', event.target.checked)}
          />
          <span>I confirm that these details are accurate.</span>
        </label>
        <label>
          <input
            type="checkbox"
            checked={draft.final_confirmation?.rights_confirmed === '1'}
            onChange={(event) => onToggle('rights_confirmed', event.target.checked)}
          />
          <span>I confirm that I have the rights to use the uploaded assets.</span>
        </label>
        <label>
          <input
            type="checkbox"
            checked={draft.final_confirmation?.website_use_ok === '1'}
            onChange={(event) => onToggle('website_use_ok', event.target.checked)}
          />
          <span>I understand this content will be used on the website build.</span>
        </label>
      </div>
    </section>
  )
}
