import React, { useEffect, useMemo, useState } from 'react'
import { api } from './api'
import { STEPS } from './steps'
import ProgressBar from './components/ProgressBar'
import HelpTip from './components/HelpTip'
import SkipButton from './components/SkipButton'
import VisualCardPicker from './components/VisualCardPicker'
import Welcome from './steps/Welcome'
import Finish from './steps/Finish'

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
const ALLERGENS = ['gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy', 'milk', 'nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs']
const DIETARY = ['vegetarian', 'vegan', 'halal', 'gluten_free']
const FONT_CHOICES = ['Playfair Display, serif', 'Fraunces, serif', 'DM Sans, sans-serif', 'Manrope, sans-serif', 'Poppins, sans-serif', 'Georgia, serif']
const PALETTES = [
  { name: 'Flame', primary: '#d83a16', accent: '#d99a22', background: '#fbf4ea', text: '#1f1712' },
  { name: 'Midnight', primary: '#2e4fff', accent: '#ffbc42', background: '#f5f7ff', text: '#171b2e' },
  { name: 'Fresh Green', primary: '#218c5a', accent: '#ffbf3a', background: '#f4fbf7', text: '#17241b' },
  { name: 'Minimal Mono', primary: '#111111', accent: '#6a6a6a', background: '#fafafa', text: '#111111' },
]

const HEADER_CHOICES = [
  { value: 'utility_header', label: 'Utility Header', description: 'Top strip for phone, email and quick trust signals.' },
  { value: 'centered_brand', label: 'Centered Brand', description: 'More editorial, useful when the logo needs room.' },
]
const HERO_CHOICES = [
  { value: 'editorial_split', label: 'Editorial Split', description: 'Balanced copy and imagery for most takeaways.' },
  { value: 'cinematic_photo', label: 'Cinematic Photo', description: 'Best when food photography should do the selling.' },
  { value: 'product_mosaic', label: 'Product Mosaic', description: 'More menu-led and modular, good for product-heavy brands.' },
]
const FOOTER_CHOICES = [
  { value: 'trust_led', label: 'Trust-led', description: 'Contact and trust cues first.' },
  { value: 'editorial', label: 'Editorial', description: 'More spacious closing section with a stronger brand feel.' },
]
const PAYMENT_CHOICES = [
  { value: 'stripe', label: 'Stripe', description: 'Clean direct-card checkout with future update path.' },
  { value: 'square', label: 'Square', description: 'Good if you also use Square hardware or POS.' },
  { value: 'sumup', label: 'SumUp', description: 'Simple option for takeaways that already use SumUp.' },
  { value: 'paypal', label: 'PayPal', description: 'Familiar fallback for many restaurants and customers.' },
  { value: 'open_banking', label: 'Open Banking', description: 'Useful if you want account-to-account payments.' },
]
const EMAIL_CHOICES = [
  { value: 'gmail', label: 'Gmail / Workspace', description: 'Use a Google account for order emails.' },
  { value: 'outlook', label: 'Outlook / Microsoft 365', description: 'Use Outlook or Microsoft 365 for order emails.' },
  { value: 'other', label: 'Other host', description: 'The developer can finish a custom SMTP relay later.' },
]
const ACCOUNTING_CHOICES = [
  { value: 'xero', label: 'Xero', description: 'Popular with UK small businesses and accountants.' },
  { value: 'quickbooks', label: 'QuickBooks', description: 'Useful if your accountant already uses Intuit.' },
  { value: 'freeagent', label: 'FreeAgent', description: 'Good for service-led small business bookkeeping.' },
]
const NEWSLETTER_CHOICES = [
  { value: 'mailchimp', label: 'Mailchimp', description: 'Email capture for offers, launches and seasonal campaigns.' },
  { value: 'later', label: 'Not right now', description: 'Skip this for now and the developer can wire it later.' },
]

function makeId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `ttos-${Math.random().toString(36).slice(2, 10)}`
}

function emptyDay() {
  return {
    closed: '0',
    open: '',
    close: '',
    collection_open: '',
    collection_close: '',
    delivery_open: '',
    delivery_close: '',
    note: '',
  }
}

function ensureDraft(data) {
  return {
    business_basics: data.business_basics || {},
    branding: data.branding || {},
    photos_media: data.photos_media || {},
    menu_content: {
      categories_tree: data.menu_content?.categories_tree || [],
      manual_menu_notes: data.menu_content?.manual_menu_notes || '',
      allergen_notes: data.menu_content?.allergen_notes || '',
      modifier_notes: data.menu_content?.modifier_notes || '',
      meal_deals: data.menu_content?.meal_deals || '',
    },
    opening_hours: {
      days: Object.fromEntries(DAYS.map((day) => [day, { ...emptyDay(), ...(data.opening_hours?.days?.[day] || {}) }])),
      special_closing_days: data.opening_hours?.special_closing_days || '',
      bank_holiday_notes: data.opening_hours?.bank_holiday_notes || '',
      preorder_preference: data.opening_hours?.preorder_preference || '0',
    },
    delivery_collection: data.delivery_collection || {},
    ordering_payment: data.ordering_payment || {},
    order_email: data.order_email || {},
    accounting: data.accounting || {},
    newsletter: data.newsletter || {},
    analytics: data.analytics || {},
    vat_legal: data.vat_legal || {},
    policies: data.policies || {},
    final_confirmation: data.final_confirmation || {},
    wizard_meta: data.wizard_meta || { skips: {} },
  }
}

function payloadForStep(stepId, draft) {
  switch (stepId) {
    case 'business_basics':
      return draft.business_basics
    case 'palette':
      return draft.branding
    case 'layout_style':
      return draft.branding
    case 'typography':
      return draft.branding
    case 'logo_photos':
      return draft.photos_media
    case 'opening_hours':
      return draft.opening_hours
    case 'menu_builder':
      return draft.menu_content
    case 'delivery_collection':
      return draft.delivery_collection
    case 'payments':
      return draft.ordering_payment
    case 'order_email':
      return {
        ...draft.order_email,
        order_notification_email: draft.ordering_payment?.order_notification_email || '',
        kitchen_notification_email: draft.ordering_payment?.kitchen_notification_email || '',
      }
    case 'accounting':
      return draft.accounting
    case 'newsletter':
      return draft.newsletter
    case 'analytics':
      return draft.analytics
    case 'vat_legal':
      return {
        ...draft.vat_legal,
        vat_number: draft.business_basics?.vat_number || '',
        company_number: draft.business_basics?.company_number || '',
        allergy_disclaimer: draft.policies?.allergy_disclaimer || '',
        refund_notes: draft.policies?.refund_notes || '',
        privacy_contact: draft.policies?.privacy_contact || '',
        terms_notes: draft.policies?.terms_notes || '',
      }
    case 'finish':
      return draft.final_confirmation
    default:
      return {}
  }
}

function textField(label, value, onChange, type = 'text', help = '') {
  return (
    <label className="ttos-intake-field">
      <span>
        {label}
        {help ? <HelpTip text={help} /> : null}
      </span>
      <input type={type} value={value || ''} onChange={(event) => onChange(event.target.value)} />
    </label>
  )
}

function textareaField(label, value, onChange, help = '') {
  return (
    <label className="ttos-intake-textarea">
      <span>
        {label}
        {help ? <HelpTip text={help} /> : null}
      </span>
      <textarea value={value || ''} onChange={(event) => onChange(event.target.value)} />
    </label>
  )
}

function UploadCard({ title, field, help, uploads, onUpload }) {
  return (
    <article className="ttos-intake-upload-card">
      <label>
        {title}
        <input
          type="file"
          onChange={(event) => {
            const [file] = Array.from(event.target.files || [])
            if (file) {
              onUpload(field, file)
            }
            event.target.value = ''
          }}
        />
        <small>{help}</small>
      </label>
      <div className="ttos-intake-upload-list">
        {(uploads?.[field] || []).map((upload) => (
          <div key={`${field}-${upload.stored_name}`} className="ttos-intake-upload-item">
            <strong>{upload.original_name}</strong>
            <span>{upload.mime || 'Stored upload'}</span>
          </div>
        ))}
      </div>
    </article>
  )
}

function ConnectorStep({ label, description, options, connectors, value, onChange, onConnect, noteValue, onNoteChange, statusValue }) {
  const selectedConnector = value ? connectors?.[value] : null
  return (
    <section className="ttos-intake-step">
      <p className="ttos-intake-step-eyebrow">{label}</p>
      <h2>{label}</h2>
      <p>{description}</p>
      <div className="ttos-intake-connector-grid">
        {options.map((option) => {
          const state = connectors?.[option.value] || {}
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
              {state.message ? <span className="ttos-intake-disabled-note">{state.message}</span> : null}
            </button>
          )
        })}
      </div>
      {value ? (
        <div className="ttos-intake-connector-note">
          <p>Selected: <strong>{options.find((option) => option.value === value)?.label || value}</strong></p>
          <div className="ttos-intake-actions">
            {selectedConnector ? (
              <>
                <button type="button" className="ttos-intake-secondary" onClick={() => onConnect(value)}>
                  {selectedConnector.available ? 'Open connect step' : 'Why is this disabled?'}
                </button>
                <span>{statusValue || 'No connection started yet.'}</span>
              </>
            ) : (
              <span>This choice does not use a direct connector in this build. Add a note below and the developer can finish it manually.</span>
            )}
          </div>
        </div>
      ) : null}
      {textareaField('Any note for your developer?', noteValue, onNoteChange)}
    </section>
  )
}

function IntakeWizard() {
  const [record, setRecord] = useState(null)
  const [draft, setDraft] = useState(() => ensureDraft({}))
  const [uploads, setUploads] = useState({})
  const [current, setCurrent] = useState(0)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const activeStep = STEPS[current]
  const connectors = record?.connectors || {}

  useEffect(() => {
    let active = true
    api.loadRecord()
      .then((payload) => {
        if (!active) return
        setRecord(payload)
        setDraft(ensureDraft(payload.submitted_data || {}))
        setUploads(payload.uploads || {})
        const index = Math.max(0, STEPS.findIndex((step) => step.id === payload.wizard?.current_step))
        setCurrent(index)
      })
      .catch((loadError) => setError(loadError.message || 'Unable to load the onboarding wizard.'))
      .finally(() => setLoading(false))
    return () => {
      active = false
    }
  }, [])

  function updateDraft(updater) {
    setDraft((previous) => ensureDraft(typeof updater === 'function' ? updater(previous) : updater))
  }

  async function saveStep(stepId = activeStep.id, payload = payloadForStep(stepId, draft), nextIndex = null) {
    setSaving(true)
    setError('')
    setNotice('')
    try {
      const response = await api.saveStep(stepId, payload)
      setRecord(response)
      setDraft(ensureDraft(response.submitted_data || {}))
      setUploads(response.uploads || {})
      setNotice('Progress saved.')
      if (typeof nextIndex === 'number') {
        setCurrent(nextIndex)
      }
    } catch (saveError) {
      setError(saveError.message || 'Unable to save this step just now.')
    } finally {
      setSaving(false)
    }
  }

  async function handleSubmit() {
    setSaving(true)
    setError('')
    setNotice('')
    try {
      const response = await api.submitFinal(payloadForStep('finish', draft))
      setRecord(response)
      setNotice('Thank you. Your onboarding wizard has been submitted.')
    } catch (submitError) {
      const messages = submitError?.payload?.errors
      setError(Array.isArray(messages) && messages.length ? messages.join(' ') : (submitError.message || 'Unable to submit yet.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleUpload(field, file) {
    setSaving(true)
    setError('')
    setNotice('')
    try {
      const response = await api.uploadField(field, file)
      setRecord(response)
      setUploads(response.uploads || {})
      setNotice(`${file.name} uploaded.`)
    } catch (uploadError) {
      setError(uploadError.message || 'Unable to upload that file.')
    } finally {
      setSaving(false)
    }
  }

  async function uploadMenuItemPhoto(categoryIndex, subIndex, itemIndex, file) {
    if (!file) return
    setSaving(true)
    setError('')
    setNotice('')
    try {
      const response = await api.uploadField('menu_item_photos', file)
      setRecord(response)
      setUploads(response.uploads || {})
      const list = response.uploads?.menu_item_photos || []
      const last = list[list.length - 1]
      if (last) {
        updateMenuItem(categoryIndex, subIndex, itemIndex, {
          image_ref: last.stored_name,
          image_name: last.original_name || file.name,
        })
        setNotice(`Photo added: ${last.original_name || file.name}`)
      }
    } catch (uploadError) {
      setError(uploadError.message || 'Unable to upload that photo.')
    } finally {
      setSaving(false)
    }
  }

  async function handleConnect(provider) {
    setSaving(true)
    setError('')
    setNotice('')
    try {
      const response = await api.startConnect(provider)
      setRecord(response.record || record)
      if (response.start?.connect_url) {
        window.open(response.start.connect_url, '_blank', 'noopener,noreferrer')
      }
      setNotice(response.start?.message || 'Connector step opened in a new tab.')
    } catch (connectError) {
      setError(connectError.message || 'Unable to open that connector.')
    } finally {
      setSaving(false)
    }
  }

  const section = useMemo(() => ({
    business: draft.business_basics || {},
    branding: draft.branding || {},
    photos: draft.photos_media || {},
    menu: draft.menu_content || {},
    hours: draft.opening_hours || { days: Object.fromEntries(DAYS.map((day) => [day, emptyDay()])) },
    delivery: draft.delivery_collection || {},
    payments: draft.ordering_payment || {},
    orderEmail: draft.order_email || {},
    accounting: draft.accounting || {},
    newsletter: draft.newsletter || {},
    analytics: draft.analytics || {},
    vatLegal: draft.vat_legal || {},
    policies: draft.policies || {},
  }), [draft])

  function nextStep() {
    if (current < STEPS.length - 1) {
      saveStep(activeStep.id, payloadForStep(activeStep.id, draft), current + 1)
    }
  }

  function previousStep() {
    if (current > 0) {
      saveStep(activeStep.id, payloadForStep(activeStep.id, draft), current - 1)
    }
  }

  function setBusiness(key, value) {
    updateDraft((previous) => ({ ...previous, business_basics: { ...previous.business_basics, [key]: value } }))
  }
  function setBranding(key, value) {
    updateDraft((previous) => ({ ...previous, branding: { ...previous.branding, [key]: value } }))
  }
  function setPhotos(key, value) {
    updateDraft((previous) => ({ ...previous, photos_media: { ...previous.photos_media, [key]: value } }))
  }
  function setDelivery(key, value) {
    updateDraft((previous) => ({ ...previous, delivery_collection: { ...previous.delivery_collection, [key]: value } }))
  }
  function setPayment(key, value) {
    updateDraft((previous) => ({ ...previous, ordering_payment: { ...previous.ordering_payment, [key]: value } }))
  }
  function setOrderEmail(key, value) {
    updateDraft((previous) => ({ ...previous, order_email: { ...previous.order_email, [key]: value } }))
  }
  function setAccounting(key, value) {
    updateDraft((previous) => ({ ...previous, accounting: { ...previous.accounting, [key]: value } }))
  }
  function setNewsletter(key, value) {
    updateDraft((previous) => ({ ...previous, newsletter: { ...previous.newsletter, [key]: value } }))
  }
  function setAnalytics(key, value) {
    updateDraft((previous) => ({ ...previous, analytics: { ...previous.analytics, [key]: value } }))
  }
  function setPolicy(key, value) {
    updateDraft((previous) => ({ ...previous, policies: { ...previous.policies, [key]: value } }))
  }
  function setVatLegal(key, value) {
    updateDraft((previous) => ({ ...previous, vat_legal: { ...previous.vat_legal, [key]: value } }))
  }
  function setFinal(key, checked) {
    updateDraft((previous) => ({
      ...previous,
      final_confirmation: { ...previous.final_confirmation, [key]: checked ? '1' : '0' },
    }))
  }

  function setDay(day, key, value) {
    updateDraft((previous) => ({
      ...previous,
      opening_hours: {
        ...previous.opening_hours,
        days: {
          ...previous.opening_hours.days,
          [day]: { ...(previous.opening_hours.days?.[day] || emptyDay()), [key]: value },
        },
      },
    }))
  }

  function setHoursMeta(key, value) {
    updateDraft((previous) => ({
      ...previous,
      opening_hours: { ...previous.opening_hours, [key]: value },
    }))
  }

  function setMenu(key, value) {
    updateDraft((previous) => ({ ...previous, menu_content: { ...previous.menu_content, [key]: value } }))
  }

  function updateCategory(index, patch) {
    const categories = [...(section.menu.categories_tree || [])]
    categories[index] = { ...categories[index], ...patch }
    setMenu('categories_tree', categories)
  }

  function addCategory() {
    setMenu('categories_tree', [...(section.menu.categories_tree || []), { id: makeId(), name: '', sub_categories: [] }])
  }

  function moveItem(list, from, to) {
    const next = [...list]
    const [item] = next.splice(from, 1)
    next.splice(to, 0, item)
    return next
  }

  function addSubCategory(categoryIndex) {
    const category = section.menu.categories_tree?.[categoryIndex]
    const next = [...(section.menu.categories_tree || [])]
    next[categoryIndex] = {
      ...category,
      sub_categories: [...(category.sub_categories || []), { id: makeId(), name: '', items: [] }],
    }
    setMenu('categories_tree', next)
  }

  function updateSubCategory(categoryIndex, subIndex, patch) {
    const next = [...(section.menu.categories_tree || [])]
    const category = next[categoryIndex]
    category.sub_categories = [...(category.sub_categories || [])]
    category.sub_categories[subIndex] = { ...category.sub_categories[subIndex], ...patch }
    setMenu('categories_tree', next)
  }

  function addMenuItem(categoryIndex, subIndex) {
    const next = [...(section.menu.categories_tree || [])]
    const category = next[categoryIndex]
    const sub = category.sub_categories[subIndex]
    sub.items = [...(sub.items || []), {
      id: makeId(),
      name: '',
      description: '',
      price: '',
      allergens: [],
      dietary: [],
      option_groups: [],
      kitchen_note: '',
    }]
    setMenu('categories_tree', next)
  }

  function updateMenuItem(categoryIndex, subIndex, itemIndex, patch) {
    const next = [...(section.menu.categories_tree || [])]
    const item = next[categoryIndex].sub_categories[subIndex].items[itemIndex]
    next[categoryIndex].sub_categories[subIndex].items[itemIndex] = { ...item, ...patch }
    setMenu('categories_tree', next)
  }

  function addOptionGroup(categoryIndex, subIndex, itemIndex) {
    const item = section.menu.categories_tree[categoryIndex].sub_categories[subIndex].items[itemIndex]
    updateMenuItem(categoryIndex, subIndex, itemIndex, {
      option_groups: [...(item.option_groups || []), { name: '', type: 'single', required: true, min: 1, max: 1, options: [] }],
    })
  }

  function updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, patch) {
    const item = section.menu.categories_tree[categoryIndex].sub_categories[subIndex].items[itemIndex]
    const groups = [...(item.option_groups || [])]
    groups[groupIndex] = { ...groups[groupIndex], ...patch }
    updateMenuItem(categoryIndex, subIndex, itemIndex, { option_groups: groups })
  }

  function addOption(categoryIndex, subIndex, itemIndex, groupIndex) {
    const item = section.menu.categories_tree[categoryIndex].sub_categories[subIndex].items[itemIndex]
    const groups = [...(item.option_groups || [])]
    groups[groupIndex] = {
      ...groups[groupIndex],
      options: [...(groups[groupIndex].options || []), { label: '', price: '0', default: false, sold_out: false }],
    }
    updateMenuItem(categoryIndex, subIndex, itemIndex, { option_groups: groups })
  }

  function updateOption(categoryIndex, subIndex, itemIndex, groupIndex, optionIndex, patch) {
    const item = section.menu.categories_tree[categoryIndex].sub_categories[subIndex].items[itemIndex]
    const groups = [...(item.option_groups || [])]
    const options = [...(groups[groupIndex].options || [])]
    options[optionIndex] = { ...options[optionIndex], ...patch }
    groups[groupIndex] = { ...groups[groupIndex], options }
    updateMenuItem(categoryIndex, subIndex, itemIndex, { option_groups: groups })
  }

  function renderStep() {
    switch (activeStep.id) {
      case 'welcome':
        return <Welcome businessName={record?.business_name} />

      case 'business_basics':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Business basics</p>
            <h2>Tell us about the restaurant</h2>
            <p>These details feed directly into the site settings, contact areas and policy drafts.</p>
            <div className="ttos-intake-form-grid">
              {textField('Takeaway name', section.business.takeaway_name, (value) => setBusiness('takeaway_name', value))}
              {textField('Legal or business name', section.business.legal_name, (value) => setBusiness('legal_name', value))}
              {textField('Tagline', section.business.tagline, (value) => setBusiness('tagline', value))}
              {textField('Phone', section.business.phone, (value) => setBusiness('phone', value), 'tel')}
              {textField('Email', section.business.email, (value) => setBusiness('email', value), 'email')}
              {textField('Website or domain', section.business.website, (value) => setBusiness('website', value), 'url')}
              {textField('Address line 1', section.business.address_1, (value) => setBusiness('address_1', value))}
              {textField('Address line 2', section.business.address_2, (value) => setBusiness('address_2', value))}
              {textField('Town / city', section.business.town, (value) => setBusiness('town', value))}
              {textField('Postcode', section.business.postcode, (value) => setBusiness('postcode', value))}
              {textField('Cuisine type', section.business.cuisine, (value) => setBusiness('cuisine', value))}
              {textField('Food hygiene rating', section.business.hygiene_rating, (value) => setBusiness('hygiene_rating', value))}
              {textField('Google Maps URL', section.business.google_maps_url, (value) => setBusiness('google_maps_url', value), 'url')}
              {textField('Food hygiene URL', section.business.hygiene_url, (value) => setBusiness('hygiene_url', value), 'url')}
            </div>
          </section>
        )

      case 'palette':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Colours</p>
            <h2>Choose the brand palette</h2>
            <p>Pick a preset to get started, then fine-tune the key colours if you want.</p>
            <div className="ttos-intake-palette-grid">
              {PALETTES.map((palette) => (
                <button
                  key={palette.name}
                  type="button"
                  className="ttos-intake-palette"
                  onClick={() => {
                    setBranding('primary_color', palette.primary)
                    setBranding('accent_color', palette.accent)
                    setBranding('background_color', palette.background)
                    setBranding('text_color', palette.text)
                  }}
                >
                  <strong>{palette.name}</strong>
                  <span className="ttos-intake-palette-swatches">
                    <i style={{ background: palette.primary }} />
                    <i style={{ background: palette.accent }} />
                    <i style={{ background: palette.background }} />
                    <i style={{ background: palette.text }} />
                  </span>
                </button>
              ))}
            </div>
            <div className="ttos-intake-form-grid">
              {textField('Primary colour', section.branding.primary_color, (value) => setBranding('primary_color', value), 'color')}
              {textField('Accent colour', section.branding.accent_color, (value) => setBranding('accent_color', value), 'color')}
              {textField('Background colour', section.branding.background_color, (value) => setBranding('background_color', value), 'color')}
              {textField('Text colour', section.branding.text_color, (value) => setBranding('text_color', value), 'color')}
            </div>
            {textareaField('Style notes', section.branding.style_notes, (value) => setBranding('style_notes', value), 'Any visual references or taste notes for the developer.')}
          </section>
        )

      case 'layout_style':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Layout style</p>
            <h2>Pick the shell of the site</h2>
            <p>These choices map into the same theme layout controls the developer can adjust later in Brand Guide.</p>
            <VisualCardPicker label="Header" options={HEADER_CHOICES} value={section.branding.header_style} onChange={(value) => setBranding('header_style', value)} />
            <VisualCardPicker label="Hero" options={HERO_CHOICES} value={section.branding.hero_style} onChange={(value) => setBranding('hero_style', value)} />
            <VisualCardPicker label="Footer" options={FOOTER_CHOICES} value={section.branding.footer_style} onChange={(value) => setBranding('footer_style', value)} />
          </section>
        )

      case 'typography':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Typography</p>
            <h2>Set the tone of the type</h2>
            <p>Choose from the quick font ideas or type your own CSS font-family stack.</p>
            <div className="ttos-intake-chip-row">
              {FONT_CHOICES.map((font) => (
                <button
                  key={font}
                  type="button"
                  className={`ttos-intake-chip${section.branding.font_heading === font ? ' is-active' : ''}`}
                  onClick={() => {
                    setBranding('font_heading', font)
                    if (!section.branding.font_body) setBranding('font_body', font)
                  }}
                >
                  {font}
                </button>
              ))}
            </div>
            <div className="ttos-intake-form-grid">
              {textField('Heading font', section.branding.font_heading, (value) => setBranding('font_heading', value))}
              {textField('Body font', section.branding.font_body, (value) => setBranding('font_body', value))}
            </div>
          </section>
        )

      case 'logo_photos':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Logo & photos</p>
            <h2>Upload the key brand assets</h2>
            <p>These uploads stay attached to the intake request for review and can also seed the Brand Guide and homepage imagery.</p>
            <div className="ttos-intake-upload-grid">
              <UploadCard title="Logo" field="branding_logo" help="PNG, SVG, JPG or WEBP." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Favicon" field="branding_favicon" help="Square icon for tabs and bookmarks." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Brand guidelines" field="branding_guidelines" help="PDF, DOC or DOCX if you have one." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Food photos" field="food_photos" help="The best food shot can become the hero image." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Shopfront photos" field="shopfront_photos" help="Useful for trust and about sections." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Interior photos" field="interior_photos" help="Optional atmosphere shots." uploads={uploads} onUpload={handleUpload} />
            </div>
            {textareaField('Social media image links', section.photos.social_media_image_links, (value) => setPhotos('social_media_image_links', value))}
            <label className="ttos-intake-toggle">
              <input
                type="checkbox"
                checked={section.photos.image_rights_confirmed === '1'}
                onChange={(event) => setPhotos('image_rights_confirmed', event.target.checked ? '1' : '0')}
              />
              <span>I confirm we have the rights to use these images on the site.</span>
            </label>
          </section>
        )

      case 'brand_summary':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Brand summary</p>
            <h2>Check the visual direction</h2>
            <div className="ttos-intake-summary-grid">
              <article><strong>Primary</strong><span>{section.branding.primary_color || 'Not set yet'}</span></article>
              <article><strong>Accent</strong><span>{section.branding.accent_color || 'Not set yet'}</span></article>
              <article><strong>Hero style</strong><span>{section.branding.hero_style || 'Choose a layout first'}</span></article>
              <article><strong>Heading font</strong><span>{section.branding.font_heading || 'Theme default'}</span></article>
            </div>
            <p>This summary is here so the client can sanity-check the direction before moving into hours and menu data.</p>
          </section>
        )

      case 'opening_hours':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Opening hours</p>
            <h2>Enter the weekly trading hours</h2>
            <div className="ttos-intake-day-grid">
              {DAYS.map((day) => (
                <article key={day} className="ttos-intake-day-card">
                  <h3>{day.charAt(0).toUpperCase() + day.slice(1)}</h3>
                  <label className="ttos-intake-toggle">
                    <input
                      type="checkbox"
                      checked={section.hours.days?.[day]?.closed === '1'}
                      onChange={(event) => setDay(day, 'closed', event.target.checked ? '1' : '0')}
                    />
                    <span>Closed</span>
                  </label>
                  <div className="ttos-intake-subgrid">
                    {textField('Open', section.hours.days?.[day]?.open, (value) => setDay(day, 'open', value), 'time')}
                    {textField('Close', section.hours.days?.[day]?.close, (value) => setDay(day, 'close', value), 'time')}
                    {textField('Collection open', section.hours.days?.[day]?.collection_open, (value) => setDay(day, 'collection_open', value), 'time')}
                    {textField('Collection close', section.hours.days?.[day]?.collection_close, (value) => setDay(day, 'collection_close', value), 'time')}
                    {textField('Delivery open', section.hours.days?.[day]?.delivery_open, (value) => setDay(day, 'delivery_open', value), 'time')}
                    {textField('Delivery close', section.hours.days?.[day]?.delivery_close, (value) => setDay(day, 'delivery_close', value), 'time')}
                  </div>
                  {textField('Day note', section.hours.days?.[day]?.note, (value) => setDay(day, 'note', value))}
                </article>
              ))}
            </div>
            <div className="ttos-intake-form-grid">
              {textareaField('Special closing days', section.hours.special_closing_days, (value) => setHoursMeta('special_closing_days', value))}
              {textareaField('Bank holiday notes', section.hours.bank_holiday_notes, (value) => setHoursMeta('bank_holiday_notes', value))}
            </div>
            <label className="ttos-intake-toggle">
              <input
                type="checkbox"
                checked={section.hours.preorder_preference === '1'}
                onChange={(event) => setHoursMeta('preorder_preference', event.target.checked ? '1' : '0')}
              />
              <span>Allow preorders when closed if possible.</span>
            </label>
          </section>
        )

      case 'menu_builder':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Menu builder</p>
            <h2>Build the menu in a structured way</h2>
            <p>Each category can hold sub-categories, then real items with prices, allergens and option groups.</p>
            <div className="ttos-intake-menu-actions">
              <button type="button" className="ttos-intake-primary" onClick={addCategory}>Add category</button>
            </div>
            <div className="ttos-intake-upload-grid">
              <UploadCard title="Menu PDF" field="menu_pdf" help="Optional reference file if you already have one." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Menu spreadsheet" field="menu_spreadsheet" help="Optional CSV/XLSX reference." uploads={uploads} onUpload={handleUpload} />
              <UploadCard title="Menu images" field="menu_images" help="Optional screenshots or legacy menu photos." uploads={uploads} onUpload={handleUpload} />
            </div>
            <div className="ttos-intake-menu-grid">
              {(section.menu.categories_tree || []).map((category, categoryIndex) => (
                <article key={category.id || categoryIndex} className="ttos-intake-menu-card">
                  <div className="ttos-intake-menu-head">
                    <h4>Category {categoryIndex + 1}</h4>
                    <div className="ttos-intake-mini-actions">
                      <button type="button" onClick={() => addSubCategory(categoryIndex)}>Add sub-category</button>
                      {categoryIndex > 0 ? <button type="button" onClick={() => setMenu('categories_tree', moveItem(section.menu.categories_tree, categoryIndex, categoryIndex - 1))}>Move up</button> : null}
                      {categoryIndex < section.menu.categories_tree.length - 1 ? <button type="button" onClick={() => setMenu('categories_tree', moveItem(section.menu.categories_tree, categoryIndex, categoryIndex + 1))}>Move down</button> : null}
                    </div>
                  </div>
                  {textField('Category name', category.name, (value) => updateCategory(categoryIndex, { name: value }))}
                  {(category.sub_categories || []).map((subCategory, subIndex) => (
                    <div key={subCategory.id || subIndex} className="ttos-intake-menu-card">
                      <div className="ttos-intake-menu-head">
                        <h5>Sub-category {subIndex + 1}</h5>
                        <div className="ttos-intake-mini-actions">
                          <button type="button" onClick={() => addMenuItem(categoryIndex, subIndex)}>Add item</button>
                        </div>
                      </div>
                      {textField('Sub-category name', subCategory.name, (value) => updateSubCategory(categoryIndex, subIndex, { name: value }))}
                      {(subCategory.items || []).map((item, itemIndex) => (
                        <div key={item.id || itemIndex} className="ttos-intake-menu-card">
                          <div className="ttos-intake-menu-head">
                            <strong>Item {itemIndex + 1}</strong>
                            <div className="ttos-intake-mini-actions">
                              <button type="button" onClick={() => addOptionGroup(categoryIndex, subIndex, itemIndex)}>Add option group</button>
                            </div>
                          </div>
                          <div className="ttos-intake-form-grid">
                            {textField('Item name', item.name, (value) => updateMenuItem(categoryIndex, subIndex, itemIndex, { name: value }))}
                            {textField('Base price', item.price, (value) => updateMenuItem(categoryIndex, subIndex, itemIndex, { price: value }), 'number')}
                          </div>
                          {textareaField('Description', item.description, (value) => updateMenuItem(categoryIndex, subIndex, itemIndex, { description: value }))}
                          <label className="ttos-intake-item-photo">
                            <span>Item photo{item.image_name ? <em> — {item.image_name} attached</em> : null}</span>
                            <input
                              type="file"
                              accept="image/jpeg,image/png,image/webp"
                              onChange={(event) => {
                                const [photo] = Array.from(event.target.files || [])
                                if (photo) {
                                  uploadMenuItemPhoto(categoryIndex, subIndex, itemIndex, photo)
                                }
                                event.target.value = ''
                              }}
                            />
                          </label>
                          <div className="ttos-intake-chip-row">
                            {ALLERGENS.map((allergen) => {
                              const active = (item.allergens || []).includes(allergen)
                              return (
                                <button
                                  key={allergen}
                                  type="button"
                                  className={`ttos-intake-chip${active ? ' is-active' : ''}`}
                                  onClick={() => updateMenuItem(categoryIndex, subIndex, itemIndex, {
                                    allergens: active ? item.allergens.filter((entry) => entry !== allergen) : [...(item.allergens || []), allergen],
                                  })}
                                >
                                  {allergen}
                                </button>
                              )
                            })}
                          </div>
                          <div className="ttos-intake-chip-row">
                            {DIETARY.map((dietary) => {
                              const active = (item.dietary || []).includes(dietary)
                              return (
                                <button
                                  key={dietary}
                                  type="button"
                                  className={`ttos-intake-chip${active ? ' is-active' : ''}`}
                                  onClick={() => updateMenuItem(categoryIndex, subIndex, itemIndex, {
                                    dietary: active ? item.dietary.filter((entry) => entry !== dietary) : [...(item.dietary || []), dietary],
                                  })}
                                >
                                  {dietary}
                                </button>
                              )
                            })}
                          </div>
                          {(item.option_groups || []).map((group, groupIndex) => (
                            <div key={`${item.id}-${groupIndex}`} className="ttos-intake-menu-card">
                              <div className="ttos-intake-menu-head">
                                <strong>Option group {groupIndex + 1}</strong>
                                <button type="button" onClick={() => addOption(categoryIndex, subIndex, itemIndex, groupIndex)}>Add option</button>
                              </div>
                              <div className="ttos-intake-form-grid">
                                {textField('Group name', group.name, (value) => updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, { name: value }))}
                                {textField('Minimum choices', group.min, (value) => updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, { min: value }), 'number')}
                                {textField('Maximum choices', group.max, (value) => updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, { max: value }), 'number')}
                              </div>
                              <div className="ttos-intake-chip-row">
                                <button type="button" className={`ttos-intake-chip${group.type === 'single' ? ' is-active' : ''}`} onClick={() => updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, { type: 'single' })}>Single choice</button>
                                <button type="button" className={`ttos-intake-chip${group.type === 'multiple' ? ' is-active' : ''}`} onClick={() => updateOptionGroup(categoryIndex, subIndex, itemIndex, groupIndex, { type: 'multiple' })}>Multiple choice</button>
                              </div>
                              {(group.options || []).map((option, optionIndex) => (
                                <div key={optionIndex} className="ttos-intake-form-grid">
                                  {textField('Option label', option.label, (value) => updateOption(categoryIndex, subIndex, itemIndex, groupIndex, optionIndex, { label: value }))}
                                  {textField('Price adjustment', option.price, (value) => updateOption(categoryIndex, subIndex, itemIndex, groupIndex, optionIndex, { price: value }), 'number')}
                                </div>
                              ))}
                            </div>
                          ))}
                        </div>
                      ))}
                    </div>
                  ))}
                </article>
              ))}
            </div>
            <div className="ttos-intake-form-grid">
              {textareaField('Manual menu notes', section.menu.manual_menu_notes, (value) => setMenu('manual_menu_notes', value))}
              {textareaField('Allergen notes', section.menu.allergen_notes, (value) => setMenu('allergen_notes', value))}
              {textareaField('Modifiers and extras notes', section.menu.modifier_notes, (value) => setMenu('modifier_notes', value))}
              {textareaField('Meal deals', section.menu.meal_deals, (value) => setMenu('meal_deals', value))}
            </div>
          </section>
        )

      case 'delivery_collection':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Delivery & collection</p>
            <h2>Set the fulfilment rules</h2>
            <div className="ttos-intake-check-list">
              <label><input type="checkbox" checked={section.delivery.collection_enabled === '1'} onChange={(event) => setDelivery('collection_enabled', event.target.checked ? '1' : '0')} /><span>Collection available</span></label>
              <label><input type="checkbox" checked={section.delivery.delivery_enabled === '1'} onChange={(event) => setDelivery('delivery_enabled', event.target.checked ? '1' : '0')} /><span>Delivery available</span></label>
            </div>
            <div className="ttos-intake-form-grid">
              {textareaField('Delivery postcode areas', section.delivery.delivery_postcodes, (value) => setDelivery('delivery_postcodes', value))}
              {textField('Delivery fee', section.delivery.delivery_fee, (value) => setDelivery('delivery_fee', value), 'number')}
              {textField('Free delivery threshold', section.delivery.free_delivery_threshold, (value) => setDelivery('free_delivery_threshold', value), 'number')}
              {textField('Minimum order', section.delivery.minimum_order, (value) => setDelivery('minimum_order', value), 'number')}
              {textField('Prep time (mins)', section.delivery.prep_time, (value) => setDelivery('prep_time', value), 'number')}
              {textField('Delivery time (mins)', section.delivery.delivery_time, (value) => setDelivery('delivery_time', value), 'number')}
            </div>
            <div className="ttos-intake-form-grid">
              {textareaField('Delivery intro text', section.delivery.delivery_intro, (value) => setDelivery('delivery_intro', value))}
              {textareaField('Collection intro text', section.delivery.collection_intro, (value) => setDelivery('collection_intro', value))}
            </div>
          </section>
        )

      case 'payments':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Payments</p>
            <h2>Choose how the website should take money</h2>
            {textField('What do you use today, if anything?', section.payments.existing_gateway, (value) => setPayment('existing_gateway', value))}
            <VisualCardPicker label="Online gateway" options={PAYMENT_CHOICES} value={section.payments.provider} onChange={(value) => setPayment('provider', value)} />
            {section.payments.provider ? (
              <div className="ttos-intake-connector-note">
                <div className="ttos-intake-actions">
                  <button type="button" className="ttos-intake-secondary" onClick={() => handleConnect(section.payments.provider)}>
                    {connectors?.[section.payments.provider]?.available ? 'Open connect step' : 'Check availability'}
                  </button>
                  <span>{connectors?.[section.payments.provider]?.message || 'Provider selected.'}</span>
                </div>
              </div>
            ) : null}
            <div className="ttos-intake-check-list">
              <label><input type="checkbox" checked={section.payments.cash_collection === '1'} onChange={(event) => setPayment('cash_collection', event.target.checked ? '1' : '0')} /><span>Cash on collection</span></label>
              <label><input type="checkbox" checked={section.payments.cash_delivery === '1'} onChange={(event) => setPayment('cash_delivery', event.target.checked ? '1' : '0')} /><span>Cash on delivery</span></label>
              <label><input type="checkbox" checked={section.payments.card_online === '1'} onChange={(event) => setPayment('card_online', event.target.checked ? '1' : '0')} /><span>Card online</span></label>
            </div>
            {textareaField('Payment notes', section.payments.payment_notes, (value) => setPayment('payment_notes', value))}
          </section>
        )

      case 'order_email':
        return (
          <ConnectorStep
            label="Order emails"
            description="Connect Gmail, Outlook, or leave a note for the developer if you use another mail host."
            options={EMAIL_CHOICES}
            connectors={connectors}
            value={section.orderEmail.provider}
            onChange={(value) => setOrderEmail('provider', value)}
            onConnect={handleConnect}
            noteValue={section.orderEmail.note}
            onNoteChange={(value) => setOrderEmail('note', value)}
            statusValue={section.orderEmail.status}
          />
        )

      case 'accounting':
        return (
          <ConnectorStep
            label="Accounting"
            description="If you already use cloud accounting, we can record that choice and point the developer to the right sync path."
            options={ACCOUNTING_CHOICES}
            connectors={connectors}
            value={section.accounting.provider}
            onChange={(value) => setAccounting('provider', value)}
            onConnect={handleConnect}
            noteValue={section.accounting.note}
            onNoteChange={(value) => setAccounting('note', value)}
            statusValue={section.accounting.status}
          />
        )

      case 'newsletter':
        return (
          <ConnectorStep
            label="Newsletter"
            description="Choose whether you want a proper email marketing platform wired behind the site’s offer capture."
            options={NEWSLETTER_CHOICES}
            connectors={connectors}
            value={section.newsletter.provider}
            onChange={(value) => setNewsletter('provider', value)}
            onConnect={handleConnect}
            noteValue={section.newsletter.note}
            onNoteChange={(value) => setNewsletter('note', value)}
            statusValue={section.newsletter.status}
          />
        )

      case 'analytics':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">Google Analytics</p>
            <h2>Paste the GA4 Measurement ID if you already have one</h2>
            <p>This is optional. If you do not have a GA4 property yet, skip it and the developer can help later.</p>
            {textField('Measurement ID', section.analytics.measurement_id, (value) => setAnalytics('measurement_id', value), 'text', 'Usually starts with G-')}
          </section>
        )

      case 'vat_legal':
        return (
          <section className="ttos-intake-step">
            <p className="ttos-intake-step-eyebrow">VAT & legal</p>
            <h2>Answer the legal basics</h2>
            <div className="ttos-intake-radio-group">
              {[
                { value: 'yes', label: 'Yes', description: 'The business is VAT registered.' },
                { value: 'no', label: 'No', description: 'The business is not VAT registered.' },
                { value: 'not_sure', label: 'Not sure', description: 'The developer needs to follow up before launch.' },
              ].map((option) => (
                <label key={option.value} className="ttos-intake-radio">
                  <input type="radio" checked={section.vatLegal.vat_registered === option.value} onChange={() => setVatLegal('vat_registered', option.value)} />
                  <span><strong>{option.label}</strong><span>{option.description}</span></span>
                </label>
              ))}
            </div>
            <div className="ttos-intake-form-grid">
              {textField('VAT number', section.business.vat_number, (value) => setBusiness('vat_number', value))}
              {textField('Company number', section.business.company_number, (value) => setBusiness('company_number', value))}
            </div>
            <div className="ttos-intake-form-grid">
              {textareaField('Allergy disclaimer note', section.policies.allergy_disclaimer, (value) => setPolicy('allergy_disclaimer', value))}
              {textareaField('Refund / cancellation note', section.policies.refund_notes, (value) => setPolicy('refund_notes', value))}
              {textareaField('Privacy / contact note', section.policies.privacy_contact, (value) => setPolicy('privacy_contact', value))}
              {textareaField('Terms note', section.policies.terms_notes, (value) => setPolicy('terms_notes', value))}
            </div>
          </section>
        )

      case 'finish':
        return <Finish draft={draft} onToggle={setFinal} />

      default:
        return null
    }
  }

  if (loading) {
    return <div className="ttos-intake-status">Loading the onboarding wizard…</div>
  }

  if (error && !record) {
    return (
      <div className="ttos-intake-error">
        <strong>We could not load the wizard.</strong> {error}
      </div>
    )
  }

  if (record?.status === 'submitted' || record?.status === 'imported') {
    return (
      <div className="ttos-intake-status">
        <strong>{record.status === 'imported' ? 'This onboarding has already been imported.' : 'Thank you.'}</strong>{' '}
        {record.status === 'imported'
          ? 'The developer has already reviewed and imported this content into the site build.'
          : 'Your onboarding details have been submitted and are waiting for review.'}
      </div>
    )
  }

  return (
    <div className="ttos-intake-app">
      <div className="ttos-intake-toolbar">
        <div className="ttos-intake-kicker">
          <strong>{activeStep.title}</strong>
          <span>Step {current + 1} of {STEPS.length}</span>
        </div>
        <ProgressBar current={current} total={STEPS.length} />
      </div>

      {notice ? <div className="ttos-intake-status">{notice}</div> : null}
      {error ? <div className="ttos-intake-error">{error}</div> : null}

      <div className="ttos-intake-layout">
        <aside className="ttos-intake-rail" aria-label="Wizard steps">
          {STEPS.map((step, index) => (
            <button
              key={step.id}
              type="button"
              className={`ttos-intake-step-link${index === current ? ' is-active' : ''}`}
              onClick={() => saveStep(activeStep.id, payloadForStep(activeStep.id, draft), index)}
            >
              <strong>{step.title}</strong>
              <span>{step.required ? 'Required' : 'Optional'}</span>
            </button>
          ))}
        </aside>

        <section className="ttos-intake-stage">
          {renderStep()}
          <div className="ttos-intake-step-footer">
            <div className="ttos-intake-actions">
              {current > 0 ? <button type="button" className="ttos-intake-secondary" onClick={previousStep}>Back</button> : null}
              {activeStep.id !== 'finish' ? (
                <>
                  <button type="button" className="ttos-intake-primary" onClick={nextStep} disabled={saving}>
                    {saving ? 'Saving…' : 'Save and continue'}
                  </button>
                  {!activeStep.required ? (
                    <SkipButton
                      onSkip={(note) => saveStep(
                        activeStep.id,
                        { ...payloadForStep(activeStep.id, draft), status: 'skipped', skip_note: note },
                        Math.min(current + 1, STEPS.length - 1),
                      )}
                    />
                  ) : (
                    <a className="ttos-intake-link" href={`mailto:${window.ttosIntake?.supportEmail || 'support@inkfire.co.uk'}`}>
                      Need help with this required step?
                    </a>
                  )}
                </>
              ) : (
                <button type="button" className="ttos-intake-primary" onClick={handleSubmit} disabled={saving}>
                  {saving ? 'Submitting…' : 'Submit onboarding'}
                </button>
              )}
            </div>
            <button type="button" className="ttos-intake-link" onClick={() => saveStep(activeStep.id)}>
              Save progress
            </button>
          </div>
        </section>
      </div>
    </div>
  )
}

export default IntakeWizard
