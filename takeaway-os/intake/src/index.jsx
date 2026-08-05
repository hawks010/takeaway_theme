import React from 'react'
import { createRoot } from 'react-dom/client'
import IntakeWizard from './IntakeWizard'
import './styles.css'

const node = document.getElementById('ttos-intake-app')

if (node) {
  createRoot(node).render(
    <React.StrictMode>
      <IntakeWizard />
    </React.StrictMode>,
  )
}
