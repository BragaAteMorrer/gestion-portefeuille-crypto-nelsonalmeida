<script setup>
import { ref, computed, onMounted, provide } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import './styles.css';
import { useRouter } from 'vue-router';

const api = async (url, options = {}) => {
  const response = await fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  let body = null;
  try {
    body = await response.json();
  } catch (e) {
    body = null;
  }

  if (!response.ok) {
    const message = body?.message || 'Une erreur est survenue';
    throw new Error(message);
  }

  return body;
};

const user = ref(null);
const authMode = ref('login');
const authForm = ref({ email: '', password: '', displayName: '', firstName: '', lastName: '', birthDate: '' });
const loading = ref(false);
const message = ref('');
const error = ref('');

const portfolio = ref([]);
const totals = ref({ invested: 0, estimatedValue: 0, realized: 0 });
const transactions = ref([]);
const lastPriceUpdate = ref(null);
const priceList = ref([]);
const currency = ref('EUR');

const newPosition = ref({ symbol: '', label: '', quantity: 0, averagePrice: 0 });
const newTransaction = ref({ symbol: '', side: 'buy', quantity: 0, price: 0 });

const isAuth = computed(() => !!user.value);
const isAdmin = computed(() => user.value?.roles?.includes('ROLE_ADMIN'));

const adminUsers = ref([]);
const adminStats = ref(null);
const adminPreviewUser = ref(null);
const adminPreviewPortfolio = ref([]);
const adminPreviewTransactions = ref([]);
const adminEditProfile = ref({ displayName: '', firstName: '', lastName: '', birthDate: '' });

const router = useRouter();

const resetMessages = () => {
  message.value = '';
  error.value = '';
};

const fetchMe = async () => {
  try {
    const data = await api('/api/me');
    user.value = data;
    await Promise.all([loadPortfolio(), loadTransactions(), loadAdminData(), loadPrices()]);
  } catch (e) {
    user.value = null;
  }
};

const submitAuth = async () => {
  resetMessages();
  loading.value = true;
  const payload = { email: authForm.value.email, password: authForm.value.password };
  if (authMode.value === 'register') {
    payload.displayName = authForm.value.displayName;
    payload.firstName = authForm.value.firstName;
    payload.lastName = authForm.value.lastName;
    payload.birthDate = authForm.value.birthDate;
  }
  try {
    const data = await api(`/api/${authMode.value}`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    user.value = data.user;
    message.value = data.message;
    await Promise.all([loadPortfolio(), loadTransactions(), loadAdminData()]);
    router.push('/');
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
};

const logout = async () => {
  resetMessages();
  await api('/api/logout', { method: 'POST' });
  user.value = null;
  portfolio.value = [];
  transactions.value = [];
  adminUsers.value = [];
  adminStats.value = null;
  adminPreviewUser.value = null;
  adminPreviewPortfolio.value = [];
  adminPreviewTransactions.value = [];
  priceList.value = [];
  router.push('/');
};

const updateProfile = async (payload) => {
  resetMessages();
  try {
    const data = await api('/api/profile', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
    user.value = data.user;
    message.value = data.message;
    await fetchMe();
  } catch (e) {
    error.value = e.message;
  }
};

const loadPortfolio = async () => {
  const data = await api('/api/portfolio');
  portfolio.value = data.items;
  totals.value = { invested: 0, estimatedValue: 0, realized: 0, ...(data.totals || {}) };
  if (data.items.length > 0) {
    lastPriceUpdate.value = new Date().toLocaleTimeString('fr-FR');
  }
};

const loadTransactions = async () => {
  const data = await api('/api/transactions');
  transactions.value = data.items;
};

const loadPrices = async () => {
  const data = await api(`/api/prices?symbols=BTC,ETH,BNB,SOL,ADA,XRP,DOGE,AVAX,LTC,LINK,TRX,ATOM,UNI,ETC,BCH,XLM,APT,NEAR,ARB&currency=${currency.value}`);
  priceList.value = data.items || [];
  if (priceList.value.length > 0) {
    lastPriceUpdate.value = new Date().toLocaleTimeString('fr-FR');
  }
};

const addPosition = async () => {
  resetMessages();
  try {
    await api('/api/portfolio', {
      method: 'POST',
      body: JSON.stringify(newPosition.value),
    });
    message.value = 'Ligne ajoutée';
    newPosition.value = { symbol: '', label: '', quantity: 0, averagePrice: 0 };
    await loadPortfolio();
  } catch (e) {
    error.value = e.message;
  }
};

const addTransaction = async () => {
  resetMessages();
  try {
    await api('/api/transactions', {
      method: 'POST',
      body: JSON.stringify(newTransaction.value),
    });
    message.value = 'Transaction enregistrée';
    newTransaction.value = { symbol: '', side: 'buy', quantity: 0, price: 0 };
    await Promise.all([loadPortfolio(), loadTransactions()]);
  } catch (e) {
    error.value = e.message;
  }
};

const deletePosition = async (id) => {
  resetMessages();
  try {
    await api(`/api/portfolio/${id}`, { method: 'DELETE' });
    await loadPortfolio();
  } catch (e) {
    error.value = e.message;
  }
};

const resetPortfolio = async () => {
  resetMessages();
  try {
    await api('/api/portfolio/reset', { method: 'POST' });
    message.value = 'Portefeuille reinitialise';
    await Promise.all([loadPortfolio(), loadTransactions()]);
  } catch (e) {
    error.value = e.message;
  }
};

const loadAdminData = async () => {
  if (!isAdmin.value) return;
  try {
    const [usersPayload, statsPayload] = await Promise.all([
      api('/api/admin/users'),
      api('/api/admin/stats'),
    ]);
    adminUsers.value = usersPayload.users;
    adminStats.value = statsPayload;
  } catch (e) {
  }
};

const loadAdminUserData = async (id) => {
  if (!isAdmin.value) return;
  resetMessages();
  try {
    const payload = await api(`/api/admin/users/${id}/portfolio`);
    adminPreviewUser.value = payload.user;
    adminPreviewPortfolio.value = payload.portfolio?.items || [];
    adminPreviewTransactions.value = payload.transactions || [];
  } catch (e) {
    error.value = e.message;
  }
};

const toggleAdmin = async (id, admin) => {
  resetMessages();
  try {
    await api(`/api/admin/users/${id}/role`, {
      method: 'PATCH',
      body: JSON.stringify({ admin }),
    });
    message.value = 'Role mis a jour';
    await loadAdminData();
  } catch (e) {
    error.value = e.message;
  }
};

const toggleSuspend = async (id, suspended) => {
  resetMessages();
  try {
    await api(`/api/admin/users/${id}/suspend`, {
      method: 'PATCH',
      body: JSON.stringify({ suspended }),
    });
    message.value = suspended ? 'Utilisateur suspendu' : 'Utilisateur reactive';
    await loadAdminData();
  } catch (e) {
    error.value = e.message;
  }
};

const deleteUserAdmin = async (id) => {
  resetMessages();
  try {
    await api(`/api/admin/users/${id}`, { method: 'DELETE' });
    message.value = 'Utilisateur supprime';
    await loadAdminData();
  } catch (e) {
    error.value = e.message;
  }
};

const adminUpdateProfile = async (id) => {
  resetMessages();
  try {
    await api(`/api/admin/users/${id}/profile`, {
      method: 'PATCH',
      body: JSON.stringify(adminEditProfile.value),
    });
    message.value = 'Profil admin mis a jour';
    await Promise.all([loadAdminData(), loadAdminUserData(id)]);
  } catch (e) {
    error.value = e.message;
  }
};

const formatCurrency = (value, cur = currency.value) =>
  new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: cur,
    maximumFractionDigits: 2,
  }).format(value);

onMounted(() => {
  fetchMe();
});

const appCtx = {
  api,
  user,
  authMode,
  authForm,
  loading,
  message,
  error,
  portfolio,
  totals,
  transactions,
  lastPriceUpdate,
  priceList,
  newPosition,
  newTransaction,
  isAuth,
  isAdmin,
  adminUsers,
  adminStats,
  adminPreviewUser,
  adminPreviewPortfolio,
  adminPreviewTransactions,
  adminEditProfile,
  currency,
  submitAuth,
  logout,
  loadPortfolio,
  loadTransactions,
  addPosition,
  addTransaction,
  deletePosition,
  resetPortfolio,
  loadAdminData,
  loadAdminUserData,
  loadPrices,
  toggleAdmin,
  toggleSuspend,
  deleteUserAdmin,
  adminUpdateProfile,
  formatCurrency,
  resetMessages,
  updateProfile,
};

provide('appCtx', appCtx);
</script>

<template>
  <div class="page">
    <nav class="topnav">
      <div class="nav-left">
        <RouterLink to="/" class="brand">Crypto Tracker</RouterLink>
        <RouterLink to="/" class="link" exact-active-class="active">Portefeuille</RouterLink>
        <RouterLink v-if="appCtx.isAuth.value" to="/profile" class="link" active-class="active">Profil</RouterLink>
        <RouterLink v-if="appCtx.isAdmin?.value" to="/admin" class="link" active-class="active">Admin</RouterLink>
      </div>
      <div class="nav-right" v-if="appCtx.isAuth.value">
        <span class="muted">{{ appCtx.user.value?.email }}</span>
        <button class="ghost" @click="appCtx.logout">Se déconnecter</button>
      </div>
    </nav>
    <RouterView />
  </div>
</template>
