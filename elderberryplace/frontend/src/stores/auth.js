import { defineStore } from 'pinia'
import * as AuthAPI from '../api/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    csrf: localStorage.getItem('csrf') || ''
  }),
  getters: {
    isAuthed: (s) => !!s.user
  },
  actions: {
    async init() {
      try {
        const user = await AuthAPI.me()
        if (user) {
          this.user = user
          localStorage.setItem('user', JSON.stringify(user))
        } else {
          this.user = null
          localStorage.removeItem('user')
        }
      } catch {
        this.user = null
        localStorage.removeItem('user')
      }
      try {
        const csrf = await AuthAPI.fetchCsrf()
        this.csrf = csrf
        window.__CSRF = csrf
        localStorage.setItem('csrf', csrf)
      } catch {
      }
    },
    async login({ username, password }) {
      const { user, csrf } = await AuthAPI.login({ username, password })
      this.user = user
      this.csrf = csrf
      window.__CSRF = csrf
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('csrf', csrf)
    },
    async logout() {
      await AuthAPI.logout()
      this.user = null
      this.csrf = ''
      window.__CSRF = ''
      localStorage.removeItem('user')
      localStorage.removeItem('csrf')
    }
  }
})