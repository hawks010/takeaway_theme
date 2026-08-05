import React from 'react'

export default function VisualCardPicker({ label, options, value, onChange }) {
  return (
    <div className="ttos-intake-visual-picker">
      <div className="ttos-intake-picker-label">{label}</div>
      <div className="ttos-intake-card-grid">
        {options.map((option) => {
          const active = value === option.value
          return (
            <button
              key={option.value}
              type="button"
              className={`ttos-intake-choice-card${active ? ' is-active' : ''}`}
              onClick={() => onChange(option.value)}
            >
              <strong>{option.label}</strong>
              <span>{option.description}</span>
            </button>
          )
        })}
      </div>
    </div>
  )
}
