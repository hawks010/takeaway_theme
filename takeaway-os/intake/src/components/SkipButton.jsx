import React, { useState } from 'react'

export default function SkipButton({ onSkip }) {
  const [open, setOpen] = useState(false)
  const [note, setNote] = useState('')

  if (!open) {
    return (
      <button type="button" className="ttos-intake-skip" onClick={() => setOpen(true)}>
        Skip this step
      </button>
    )
  }

  return (
    <div className="ttos-intake-skip-note">
      <label>
        Optional note for your developer
        <input
          type="text"
          value={note}
          onChange={(event) => setNote(event.target.value)}
          placeholder="e.g. We already have this handled elsewhere"
        />
      </label>
      <div className="ttos-intake-inline-actions">
        <button type="button" className="ttos-intake-skip" onClick={() => onSkip(note)}>
          Save skip note
        </button>
        <button type="button" className="ttos-intake-link" onClick={() => setOpen(false)}>
          Cancel
        </button>
      </div>
    </div>
  )
}
