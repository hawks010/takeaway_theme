import React from 'react'

export default function ProgressBar({ current, total }) {
  const pct = total <= 1 ? 0 : Math.round((current / (total - 1)) * 100)
  return (
    <div
      role="progressbar"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={pct}
      className="ttos-intake-progress"
    >
      <div className="ttos-intake-progress-fill" style={{ width: `${pct}%` }} />
    </div>
  )
}
