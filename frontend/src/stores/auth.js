import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    initialized: false,
    setupRequired: false,
    user: null,
    csrf: null,
    error: '',
  }),
  actions: {
    async bootstrap() {
      const response = await fetch('/api/v1/auth/status', { credentials: 'same-origin' })
      const data = await response.json()
      this.setupRequired = !!data.setup_required
      this.user = data.user || null
      this.csrf = data.csrf_token || null
      this.initialized = true
      return data
    },
    async setup(payload) {
      this.error = ''
      const response = await fetch('/api/v1/auth/setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      })
      const data = await response.json()
      if (!response.ok) {
        this.error = data.error || 'Unable to complete setup.'
        throw new Error(this.error)
      }
      this.user = data.user
      this.csrf = data.csrf_token
      this.setupRequired = false
      return data
    },
    async login(payload) {
      this.error = ''
      const response = await fetch('/api/v1/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      })
      const data = await response.json()
      if (!response.ok) {
        this.error = data.error || 'Login failed.'
        throw new Error(this.error)
      }
      this.user = data.user
      this.csrf = data.csrf_token
      return data
    },
    async logout() {
      if (this.user) {
        await fetch('/api/v1/auth/logout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf || '' },
          credentials: 'same-origin',
        })
      }
      this.user = null
      this.csrf = null
    },
  },
})
