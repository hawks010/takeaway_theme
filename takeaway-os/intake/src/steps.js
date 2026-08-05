export const STEPS = [
  { id: 'welcome', required: true, title: 'Welcome' },
  { id: 'business_basics', required: true, title: 'Business basics' },
  { id: 'palette', required: true, title: 'Colours' },
  { id: 'layout_style', required: true, title: 'Layout style' },
  { id: 'typography', required: true, title: 'Typography' },
  { id: 'logo_photos', required: true, title: 'Logo & photos' },
  { id: 'brand_summary', required: true, title: 'Brand summary' },
  { id: 'opening_hours', required: true, title: 'Opening hours' },
  { id: 'menu_builder', required: true, title: 'Menu builder' },
  { id: 'delivery_collection', required: true, title: 'Delivery & collection' },
  { id: 'payments', required: true, title: 'Payments' },
  { id: 'order_email', required: false, title: 'Order emails' },
  { id: 'accounting', required: false, title: 'Accounting' },
  { id: 'newsletter', required: false, title: 'Newsletter' },
  { id: 'analytics', required: false, title: 'Google Analytics' },
  { id: 'vat_legal', required: true, title: 'VAT & legal' },
  { id: 'finish', required: true, title: 'Finish' },
]

export function stepIndex(id) {
  return STEPS.findIndex((step) => step.id === id)
}
