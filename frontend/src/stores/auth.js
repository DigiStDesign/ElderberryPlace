// This Pinia store manages authentication state (user + csrf) and wraps AuthAPI calls.

import { defineStore } from 'pinia'
import * as AuthAPI from '../api/auth'

export const useAuthStore = defineStore('auth', {
  // State is initialized from localStorage to persist sessions across reloads.
  state: () => ({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    csrf: localStorage.getItem('csrf') || ''
  }),
  getters: {
    // Returns true/false if a user is logged in.
    isAuthed: (s) => !!s.user
  },
  actions: {
    /**
     * init(): syncs with backend /me and /csrf to restore state at app start.
     */
    async init() {
      try {
        const response = await AuthAPI.me()
        const root = response || {}
        const data = root?.data || root
        const user = data?.user || data
        if (user) {
          // Save user to state and localStorage
          this.user = user
          localStorage.setItem('user', JSON.stringify(user))
        } else {
          // Clear user state and localStorage if not authed
          this.user = null
          localStorage.removeItem('user')
        }
      } catch {
        // On error, clear user state and localStorage
        this.user = null
        localStorage.removeItem('user')
      }
      try {
        const csrf = await AuthAPI.csrf()
        this.csrf = csrf
        window.__CSRF = csrf
        // Save csrf token to localStorage
        localStorage.setItem('csrf', csrf)
      } catch {
        // CSRF fetch failed; leave csrf empty
      }
    },
    /**
     * login(): calls AuthAPI.login, validates a user is returned, saves user and csrf to state and localStorage.
     */
    async login({ username, password }) {
      const response = await AuthAPI.login({ username, password })
      const root = response || {}
      const data = root?.data || root
      const user = data?.user || data
      if (!user) {
        throw new Error(`Login failed: no user returned, response: ${JSON.stringify(response)}`)
      }
      // Save user to state and localStorage
      this.user = user
      if (data.csrf || response.csrf) {
        const token = data.csrf || response.csrf
        this.csrf = token
        window.__CSRF = token
        localStorage.setItem('csrf', token)
      }
      localStorage.setItem('user', JSON.stringify(user))
    },
    /**
     * logout(): clears state/localStorage and calls backend logout.
     */
    async logout() {
      await AuthAPI.logout()
      // Clear user and csrf from state, global, and localStorage
      this.user = null
      this.csrf = ''
      window.__CSRF = ''
      localStorage.removeItem('user')
      localStorage.removeItem('csrf')
    }
  }
})