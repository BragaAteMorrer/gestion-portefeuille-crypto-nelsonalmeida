import { createRouter, createWebHistory } from 'vue-router';
import PortfolioView from '../views/PortfolioView.vue';
import AdminView from '../views/AdminView.vue';
import ProfileView from '../views/ProfileView.vue';

const routes = [
  { path: '/', name: 'portfolio', component: PortfolioView },
  { path: '/admin', name: 'admin', component: AdminView },
  { path: '/profile', name: 'profile', component: ProfileView },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
