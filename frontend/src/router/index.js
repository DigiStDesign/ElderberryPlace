// This file sets up Vue Router with route definitions and guards.

import { createRouter, createWebHistory } from 'vue-router'  // Import router helpers
import { requireAuth, redirectIfAuthed } from './guards'        // Import route guards

import HomeView from '../pages/HomeView.vue'                   // Import views
import LoginView from '../pages/LoginView.vue'
import ResidentsList from '../pages/residents/ResidentsList.vue'
import ResidentForm from '../pages/residents/ResidentForm.vue'
import ResidentDetail from '../pages/residents/ResidentDetail.vue'


// Lazy-loaded components: these routes are code-split and only loaded when visited.
const StaffList = () => import('../pages/staff/StaffList.vue')
const StaffForm = () => import('../pages/staff/StaffForm.vue')
const StaffDetail = () => import('../pages/staff/StaffDetail.vue')
const VisitorsList = () => import('../pages/visitors/VisitorsList.vue')
const VisitorForm = () => import('../pages/visitors/VisitorForm.vue')
const ServicesList = () => import('../pages/services/ServicesList.vue')
const VisitsList = () => import('../pages/visits/VisitsList.vue')
const ScheduleView = () => import('../pages/schedule/ScheduleView.vue')
const CategoriesList = () => import('../pages/categories/CategoriesList.vue')
const RelationshipsView = () => import('../pages/relationships/RelationshipsView.vue')

const ServiceForm = () => import('../pages/services/ServiceForm.vue')
const VisitForm = () => import('../pages/visits/VisitForm.vue')
const ScheduleForm = () => import('../pages/schedule/ScheduleForm.vue')
const CategoryForm = () => import('../pages/categories/CategoryForm.vue')
const RelationshipForm = () => import('../pages/relationships/RelationshipForm.vue')

// Define the app’s routes and apply guards like requireAuth and redirectIfAuthed.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    // Redirect root to login
    { path: '/', redirect: '/login' },
    { path: '/home', component: HomeView, beforeEnter: requireAuth },

    // Load login form, redirect if already authenticated
    { path: '/login', component: LoginView, beforeEnter: redirectIfAuthed },

    // Residents routes: listing, detail, and editing residents
    { path: '/residents', component: ResidentsList, beforeEnter: requireAuth },
    { path: '/residents/new', component: ResidentForm, beforeEnter: requireAuth },
    { path: '/residents/:id', component: ResidentDetail, beforeEnter: requireAuth, props: true },
    { path: '/residents/:id/edit', component: ResidentForm, beforeEnter: requireAuth, props: true },

    // Staff section of the app
    { path: '/staff', component: StaffList, beforeEnter: requireAuth },
    { path: '/staff/new', component: StaffForm, beforeEnter: requireAuth },
    { path: '/staff/:id', component: StaffDetail, beforeEnter: requireAuth, props: true },
    { path: '/staff/:id/edit', component: StaffForm, beforeEnter: requireAuth, props: true },

    // Visitors section of the app
    { path: '/visitors',          name: 'visitors.list', component: VisitorsList,  beforeEnter: requireAuth },
    { path: '/visitors/new',      name: 'visitors.new',  component: VisitorForm,   beforeEnter: requireAuth },
    { path: '/visitors/:id/edit', name: 'visitors.edit', component: VisitorForm,   beforeEnter: requireAuth, props: true },

    // Services section of the app
    { path: '/services',            name: 'services.list', component: ServicesList, beforeEnter: requireAuth },
    { path: '/services/new',        name: 'services.new',  component: ServiceForm,  beforeEnter: requireAuth },
    { path: '/services/:id/edit',   name: 'services.edit', component: ServiceForm,  beforeEnter: requireAuth, props: true },

    // Visits section of the app
    { path: '/visits', component: VisitsList, beforeEnter: requireAuth },
    { path: '/visits/new', component: VisitForm, beforeEnter: requireAuth },
    { path: '/visits/:id/edit', component: VisitForm, beforeEnter: requireAuth, props: true },

    // Schedule section of the app
    { path: '/schedule', component: ScheduleView, beforeEnter: requireAuth },
    { path: '/schedule/new', component: ScheduleForm, beforeEnter: requireAuth },
    { path: '/schedule/:id/edit', component: ScheduleForm, beforeEnter: requireAuth, props: true },

    // Categories section of the app
    { path: '/categories', component: CategoriesList, beforeEnter: requireAuth },
    { path: '/categories/new', component: CategoryForm, beforeEnter: requireAuth },
    { path: '/categories/:id/edit', component: CategoryForm, beforeEnter: requireAuth, props: true },

    // Relationships section of the app
    { path: '/relationships', component: RelationshipsView, beforeEnter: requireAuth },
    { path: '/relationships/new', component: RelationshipForm, beforeEnter: requireAuth },
    { path: '/relationships/:id/edit', component: RelationshipForm, beforeEnter: requireAuth, props: true },

    // Redirect unknown routes to home
    { path: '/:pathMatch(.*)*', redirect: '/' }
  ]
})

export default router