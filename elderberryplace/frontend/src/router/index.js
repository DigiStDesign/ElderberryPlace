import { createRouter, createWebHistory } from 'vue-router'
import { requireAuth, redirectIfAuthed } from './guards'

import HomeView from '../pages/HomeView.vue'
import LoginView from '../pages/LoginView.vue'
import ResidentsList from '../pages/residents/ResidentsList.vue'
import ResidentForm from '../pages/residents/ResidentForm.vue'


const StaffList = () => import('../pages/staff/StaffList.vue')
const VisitorsList = () => import('../pages/visitors/VisitorsList.vue')
const ServicesList = () => import('../pages/services/ServicesList.vue')
const VisitsList = () => import('../pages/visits/VisitsList.vue')
const ScheduleView = () => import('../pages/schedule/ScheduleView.vue')
const CategoriesList = () => import('../pages/categories/CategoriesList.vue')
const RelationshipsView = () => import('../pages/relationships/RelationshipsView.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomeView, beforeEnter: requireAuth },
    { path: '/login', component: LoginView, beforeEnter: redirectIfAuthed },

    { path: '/residents', component: ResidentsList, beforeEnter: requireAuth },
    { path: '/residents/new', component: ResidentForm, beforeEnter: requireAuth },
    { path: '/residents/:id', component: ResidentForm, beforeEnter: requireAuth, props: true },

    { path: '/staff', component: StaffList, beforeEnter: requireAuth },
    { path: '/visitors', component: VisitorsList, beforeEnter: requireAuth },
    { path: '/services', component: ServicesList, beforeEnter: requireAuth },
    { path: '/visits', component: VisitsList, beforeEnter: requireAuth },
    { path: '/schedule', component: ScheduleView, beforeEnter: requireAuth },
    { path: '/categories', component: CategoriesList, beforeEnter: requireAuth },
    { path: '/relationships', component: RelationshipsView, beforeEnter: requireAuth },

    { path: '/:pathMatch(.*)*', redirect: '/' }
  ]
})

export default router