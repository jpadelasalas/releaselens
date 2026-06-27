import api from './api'

export async function prepareCsrfCookie() {
  await api.get('/api/v1/auth/csrf-cookie')
}
