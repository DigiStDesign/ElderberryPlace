import api from './client'

/** Medication adherence summary */
export async function medAdherence(params) {
  const { data } = await api.get('/reports/med-adherence', { params })
  return data.data.summary
}

/** Medication missed-by-shift rows */
export async function medMissedByShift(params) {
  const { data } = await api.get('/reports/med-missed-by-shift', { params })
  return data.data.rows
}

/** ICT-212: Staff workload (returns { from, to, rows }) */
export async function staffWorkload(params) {
  const { data } = await api.get('/reports/staff-workload', { params })
  return data.data
}

/** ICT-213: Compliance summary (returns { from, to, rows }) */
export async function complianceSummary(params) {
  const { data } = await api.get('/reports/compliance-summary', { params })
  return data.data
}
