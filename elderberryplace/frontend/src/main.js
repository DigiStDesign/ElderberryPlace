// Entry point of the Vue app; it creates the app, sets up Pinia for state management, installs the router, and mounts the root component.
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'

// Creating the Vue application using App.vue as the root component
const app = createApp(App)

// Adding Pinia as the global store
app.use(createPinia())

// Registering the router for navigation between views
app.use(router)

// Mounting the Vue app into the #app div in index.html
app.mount('#app')