// This file sets up Vue Router with route definitions and guards.

import { createRouter, createWebHistory } from 'vue-router'  // Import router helpers
import { requireAuth, redirectIfAuthed } from './guards'        // Import route guards

import HomeView from '../pages/HomeView.vue'                   // Import views
import LoginView from '../pages/LoginView.vue'
import ResidentsList from '../pages/residents/ResidentsList.vue'
import ResidentForm from '../pages/residents/ResidentForm.vue'


// Lazy-loaded components: these routes are code-split and only loaded when visited.
const StaffList = () => import('../pages/staff/StaffList.vue')
const VisitorsList = () => import('../pages/visitors/VisitorsList.vue')
const ServicesList = () => import('../pages/services/ServicesList.vue')
const VisitsList = () => import('../pages/visits/VisitsList.vue')
const ScheduleView = () => import('../pages/schedule/ScheduleView.vue')
const CategoriesList = () => import('../pages/categories/CategoriesList.vue')
const RelationshipsView = () => import('../pages/relationships/RelationshipsView.vue')

// Define the app’s routes and apply guards like requireAuth and redirectIfAuthed.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    // Show home dashboard, requires authentication
    { path: '/', component: HomeView, beforeEnter: requireAuth },
    { path: '/home', component: HomeView, beforeEnter: requireAuth },

    // Load login form, redirect if already authenticated
    { path: '/login', component: LoginView, beforeEnter: redirectIfAuthed },

    // Residents routes: listing and editing residents
    { path: '/residents', component: ResidentsList, beforeEnter: requireAuth },
    { path: '/residents/new', component: ResidentForm, beforeEnter: requireAuth },
    { path: '/residents/:id', component: ResidentForm, beforeEnter: requireAuth, props: true },

    // Staff section of the app
    { path: '/staff', component: StaffList, beforeEnter: requireAuth },

    // Visitors section of the app
    { path: '/visitors', component: VisitorsList, beforeEnter: requireAuth },

    // Services section of the app
    { path: '/services', component: ServicesList, beforeEnter: requireAuth },

    // Visits section of the app
    { path: '/visits', component: VisitsList, beforeEnter: requireAuth },

    // Schedule section of the app
    { path: '/schedule', component: ScheduleView, beforeEnter: requireAuth },

    // Categories section of the app
    { path: '/categories', component: CategoriesList, beforeEnter: requireAuth },

    // Relationships section of the app
    { path: '/relationships', component: RelationshipsView, beforeEnter: requireAuth },

    // Redirect unknown routes to home
    { path: '/:pathMatch(.*)*', redirect: '/' }
  ]
})

export default router