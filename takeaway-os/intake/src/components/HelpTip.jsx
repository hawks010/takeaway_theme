import React, { useState } from 'react'

export default function HelpTip({ text }) {
  const [open, setOpen] = useState(false)

  return (
    <span className="ttos-intake-help">
      <button
        type="button"
        aria-expanded={open}
        aria-label="Help for this step"
        onClick={() => setOpen((value) => !value)}
      >
        ?
      </button>
      {open && <span className="ttos-intake-help-note">{text}</span>}
    </span>
  )
}
