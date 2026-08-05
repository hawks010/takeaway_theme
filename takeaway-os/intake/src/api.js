const cfg = () => window.ttosIntake || {}

async function parseResponse(res) {
  const json = await res.json().catch(() => ({}))
  if (!res.ok) {
    const error = new Error(json.message || `HTTP ${res.status}`)
    error.payload = json
    throw error
  }
  return json
}

async function request(path, method = 'POST', body = null) {
  const { apiBase, intakeId, token } = cfg()
  const res = await fetch(`${apiBase}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...(body || {}), intake_id: intakeId, token }),
  })
  return parseResponse(res)
}

export const api = {
  loadRecord() {
    return request('/intake/record')
  },
  saveStep(stepId, data) {
    return request('/intake/save-step', 'POST', { step_id: stepId, data })
  },
  submitFinal(data) {
    return request('/intake/submit', 'POST', { data })
  },
  startConnect(provider) {
    return request(`/intake/connect/${provider}/start`, 'POST')
  },
  async uploadField(field, file) {
    const { apiBase, intakeId, token } = cfg()
    const form = new FormData()
    form.append('intake_id', intakeId)
    form.append('token', token)
    form.append('field', field)
    form.append(field, file)

    const res = await fetch(`${apiBase}/intake/upload`, {
      method: 'POST',
      body: form,
    })
    return parseResponse(res)
  },
}
