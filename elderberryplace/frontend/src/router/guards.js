import { useAuthStore } from '../stores/auth'

export function requireAuth(to, from, next) {
  const auth = useAuthStore()
  if (auth.isAuthed) next()
  else next('/login')
}

export function redirectIfAuthed(to, from, next) {
  const auth = useAuthStore()
  if (auth.isAuthed) next('/')
  else next()
}