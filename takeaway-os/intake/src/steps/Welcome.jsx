import React from 'react'

export default function Welcome({ businessName }) {
  return (
    <section className="ttos-intake-step">
      <p className="ttos-intake-step-eyebrow">Welcome</p>
      <h2>Thanks for choosing Inkfire</h2>
      <p>
        This private onboarding link is for {businessName || 'your restaurant'}.
        It autosaves as you go, and you can come back to this exact link later.
      </p>
      <div className="ttos-intake-highlight-grid">
        <article>
          <strong>Roughly 20 minutes</strong>
          <span>Answer the essentials now and leave optional connections for later if needed.</span>
        </article>
        <article>
          <strong>No WordPress login</strong>
          <span>Everything here is collected securely through your private build link.</span>
        </article>
        <article>
          <strong>Built for real setup</strong>
          <span>Branding, menu, delivery rules, policies, and launch-ready notes all feed back to the dev team.</span>
        </article>
      </div>
    </section>
  )
}
