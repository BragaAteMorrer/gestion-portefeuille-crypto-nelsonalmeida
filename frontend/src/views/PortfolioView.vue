<script setup>
import { inject, onMounted, ref, computed } from 'vue';
import Chart from 'chart.js/auto';

const appCtx = inject('appCtx');
const selectedSymbols = ref([]);
const historyMap = ref({});
let chartInstance = null;
const selectedRange = ref('1d');
const startForRange = (range) => (range === 'from2025' ? '2025-01-01' : null);
const priceFilter = ref('');
const portfolioFilter = ref('');
const txFilter = ref('');

const currentPriceFor = (symbol) => {
  const found = appCtx.priceList.value.find((p) => p.symbol === symbol);
  return found ? found.price : 0;
};

const renderChart = () => {
  const ctx = document.getElementById('priceChart');
  if (!ctx) return;
  if (chartInstance) {
    chartInstance.destroy();
  }
  const labelsSet = new Set();
  Object.values(historyMap.value).forEach((list) => {
    list.forEach((d) => labelsSet.add(d.collectedAt));
  });
  const labels = Array.from(labelsSet).sort();

  const datasets = Object.entries(historyMap.value).map(([symbol, list], idx) => {
    const map = new Map(list.map((d) => [d.collectedAt, d.price]));
    const dataPoints = labels.map((ts) => map.get(ts) ?? null);
    const colors = ['#6cf', '#7bd88f', '#ffb86c', '#ff6b6b', '#c792ea', '#64d8cb', '#f2d600', '#9ee493'];
    return {
      label: `${symbol} (${appCtx.currency.value})`,
      data: dataPoints,
      borderColor: colors[idx % colors.length],
      tension: 0.2,
      spanGaps: true,
    };
  });

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets,
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { ticks: { autoSkip: true, maxTicksLimit: 6 } },
        y: { beginAtZero: false },
      },
      plugins: {
        legend: { display: true },
      },
    },
  });
};

const loadHistory = async () => {
  const symbols = selectedSymbols.value.length
    ? selectedSymbols.value
    : appCtx.priceList.value[0]
      ? [appCtx.priceList.value[0].symbol]
      : [];
  const newMap = {};
  for (const sym of symbols) {
    try {
      const start = startForRange(selectedRange.value);
      const limit = start ? 10000 : 1000;
      const params = new URLSearchParams({
        symbol: sym,
        limit: String(limit),
        range: start ? 'all' : selectedRange.value,
        currency: appCtx.currency.value,
      });
      if (start) {
        params.append('start', start);
      }
      const res = await appCtx.api(`/api/prices/history?${params.toString()}`);
      newMap[sym] = res.items || [];
    } catch (e) {
    }
  }
  historyMap.value = newMap;
  renderChart();
};

const refreshAll = async () => {
  if (!appCtx.isAuth.value) return;
  await appCtx.loadPrices();
  if (selectedSymbols.value.length === 0 && appCtx.priceList.value.length > 0) {
    selectedSymbols.value = [appCtx.priceList.value[0].symbol];
  }
  await loadHistory();
};

const onCurrencyChange = async () => {
  await refreshAll();
};

const toggleSymbol = async (symbol) => {
  const exists = selectedSymbols.value.includes(symbol);
  selectedSymbols.value = exists
    ? selectedSymbols.value.filter((s) => s !== symbol)
    : [...selectedSymbols.value, symbol];
  await loadHistory();
};

const onSymbolSelect = (sym) => {
  appCtx.newPosition.value.symbol = sym;
  const cp = currentPriceFor(sym);
  appCtx.newPosition.value.averagePrice = cp;
};

const onTransactionSymbolSelect = (sym) => {
  appCtx.newTransaction.value.symbol = sym;
  const cp = currentPriceFor(sym);
  appCtx.newTransaction.value.price = cp;
};

const filteredPrices = computed(() => {
  const q = priceFilter.value.trim().toUpperCase();
  if (!q) return appCtx.priceList.value;
  return appCtx.priceList.value.filter((p) => p.symbol.toUpperCase().includes(q));
});

const filteredPortfolio = computed(() => {
  const q = portfolioFilter.value.trim().toUpperCase();
  if (!q) return appCtx.portfolio.value;
  return appCtx.portfolio.value.filter((p) => (p.symbol?.toUpperCase() || '').includes(q) || (p.label || '').toUpperCase().includes(q));
});

const filteredTransactions = computed(() => {
  const q = txFilter.value.trim().toUpperCase();
  if (!q) return appCtx.transactions.value;
  return appCtx.transactions.value.filter((tx) => (tx.symbol?.toUpperCase() || '').includes(q) || tx.side.toUpperCase().includes(q));
});

onMounted(() => {
  refreshAll();
});
</script>

<template>
  <main>
    <section class="hero">
      <div>
        <p class="eyebrow">Portefeuille</p>
        <h1>Suivez vos actifs avec une interface claire et securisee.</h1>
        <p class="subhead">
          Enregistrez vos positions, gardez un historique des transactions et visualisez la valeur globale de votre
          portefeuille.
        </p>
      </div>
      <div class="cta-panel" v-if="!appCtx.isAuth.value">
        <div class="tabs">
          <button :class="{ active: appCtx.authMode.value === 'login' }" @click="appCtx.authMode.value = 'login'">Connexion</button>
          <button :class="{ active: appCtx.authMode.value === 'register' }" @click="appCtx.authMode.value = 'register'">Inscription</button>
        </div>
        <form @submit.prevent="appCtx.submitAuth">
          <label>Email
            <input type="email" v-model="appCtx.authForm.value.email" required>
          </label>
          <label>Mot de passe
            <input type="password" v-model="appCtx.authForm.value.password" required>
          </label>
          <template v-if="appCtx.authMode.value === 'register'">
            <label>Pseudo
              <input type="text" v-model="appCtx.authForm.value.displayName" placeholder="ex: Alice">
            </label>
            <label>Nom
              <input type="text" v-model="appCtx.authForm.value.lastName" placeholder="Dupont">
            </label>
            <label>Prenom
              <input type="text" v-model="appCtx.authForm.value.firstName" placeholder="Alice">
            </label>
            <label>Date de naissance
              <input type="date" v-model="appCtx.authForm.value.birthDate">
            </label>
          </template>
          <button type="submit" :disabled="appCtx.loading.value">{{ appCtx.authMode.value === 'login' ? 'Connexion' : 'Creer mon compte' }}</button>
        </form>
        <p class="info">Sessions securisees cote serveur (Symfony).</p>
      </div>
      <div class="cta-panel" v-else>
        <p class="eyebrow">Bienvenue</p>
        <h3>{{ appCtx.user.value.displayName }}</h3>
        <p>{{ appCtx.user.value.email }}</p>
      </div>
    </section>

    <section v-if="appCtx.error.value || appCtx.message.value" class="feedback">
      <p v-if="appCtx.error.value" class="error">{{ appCtx.error.value }}</p>
      <p v-if="appCtx.message.value" class="success">{{ appCtx.message.value }}</p>
    </section>

    <section v-if="appCtx.isAuth.value" class="dashboard">
      <div class="stat-card">
        <p>Total investi</p>
        <h2>{{ appCtx.formatCurrency(appCtx.totals.value.invested || 0) }}</h2>
      </div>
      <div class="stat-card">
        <p>Valeur estimee</p>
        <h2>{{ appCtx.formatCurrency(appCtx.totals.value.estimatedValue || 0) }}</h2>
      </div>
      <div class="stat-card">
        <p>Benefice realise (ventes)</p>
        <h2 :class="[(appCtx.totals.value.realized || 0) >= 0 ? 'pl-pos' : 'pl-neg']">
          {{ appCtx.formatCurrency(appCtx.totals.value.realized || 0) }}
        </h2>
      </div>
      <div class="stat-card">
        <p>Mise a jour des cours</p>
        <h4>{{ appCtx.lastPriceUpdate.value || 'En attente...' }}</h4>
      </div>
    </section>

    <section v-if="appCtx.isAuth.value" class="live-prices">
      <h3>Cours actuels</h3>
      <div class="filter-row">
        <button class="ghost" @click="refreshAll">Rafraichir les cours</button>
      </div>
      <div class="cards">
        <div
          class="stat-card selectable"
          v-for="item in filteredPrices"
          :key="item.symbol"
          :class="{ selected: selectedSymbols.includes(item.symbol) }"
          @click="() => toggleSymbol(item.symbol)"
        >
          <p>{{ item.symbol }}</p>
          <h3>{{ appCtx.formatCurrency(item.price || 0) }}</h3>
          <p class="muted">Maj : {{ item.updatedAt ? new Date(item.updatedAt).toLocaleTimeString('fr-FR') : 'N/A' }}</p>
          <p class="muted">24h :
            <span :class="[(item.change24h || 0) >= 0 ? 'pl-pos' : 'pl-neg']">
              {{ item.change24hPct !== null && item.change24hPct !== undefined ? item.change24hPct.toFixed(2) + '%' : 'N/A' }}
            </span>
          </p>
        </div>
        <p v-if="appCtx.priceList.value.length === 0" class="muted">Pas de cours pour le moment.</p>
      </div>
      <div class="chart-card">
        <canvas id="priceChart" height="260"></canvas>
      </div>
      <div class="filter-row">
        <label>Plage
          <select v-model="selectedRange" @change="loadHistory">
            <option value="1h">Derniere heure</option>
            <option value="6h">Dernieres 6 heures</option>
            <option value="1d">Dernier jour</option>
            <option value="1w">Derniere semaine</option>
            <option value="2w">Deux semaines</option>
            <option value="1m">Dernier mois</option>
            <option value="6m">6 mois</option>
            <option value="1y">1 an</option>
            <option value="all">Tout</option>
          </select>
        </label>
      </div>
    </section>

    <section v-if="appCtx.isAuth.value" class="forms-grid">
      <div class="form-card">
        <h3>Ajouter une position</h3>
        <form @submit.prevent="appCtx.addPosition">
          <label>Symbole
            <select v-model="appCtx.newPosition.value.symbol" @change="(e) => onSymbolSelect(e.target.value)">
              <option v-for="item in appCtx.priceList.value" :key="item.symbol" :value="item.symbol">{{ item.symbol }}</option>
            </select>
          </label>
          <label>Libelle
            <input v-model="appCtx.newPosition.value.label" placeholder="Bitcoin">
          </label>
          <label>Quantite
            <input type="number" step="0.00000001" v-model.number="appCtx.newPosition.value.quantity" required>
          </label>
          <label>Prix moyen (EUR) (auto)
            <input type="number" step="0.01" v-model.number="appCtx.newPosition.value.averagePrice" readonly>
          </label>
          <button type="submit">Enregistrer</button>
        </form>
      </div>
      <div class="form-card">
        <h3>Enregistrer une transaction</h3>
        <form @submit.prevent="appCtx.addTransaction">
          <label>Symbole
            <select v-model="appCtx.newTransaction.value.symbol" @change="(e) => onTransactionSymbolSelect(e.target.value)">
              <option v-for="item in appCtx.priceList.value" :key="item.symbol" :value="item.symbol">{{ item.symbol }}</option>
            </select>
          </label>
          <label>Type
            <select v-model="appCtx.newTransaction.value.side">
              <option value="buy">Achat</option>
              <option value="sell">Vente</option>
            </select>
          </label>
          <label>Quantite
            <input type="number" step="0.00000001" v-model.number="appCtx.newTransaction.value.quantity" required>
          </label>
          <label>Prix (EUR) (auto)
            <input type="number" step="0.01" v-model.number="appCtx.newTransaction.value.price" readonly>
          </label>
          <button type="submit">Ajouter</button>
        </form>
      </div>
    </section>

<section v-if="appCtx.isAuth.value" class="tables">
      <div class="table-card">
        <div class="table-header">
          <h3>Portefeuille</h3>
          <div class="header-actions">
            <span>{{ appCtx.portfolio.value.length }} lignes</span>
            <input v-model="portfolioFilter" placeholder="Filtrer symbole/label">
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Actif</th>
              <th>Quantite</th>
              <th>Prix moyen</th>
              <th>Cours actuel</th>
              <th>Valeur estimee</th>
              <th>P/L</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredPortfolio.length === 0">
              <td colspan="7" class="empty">Ajoutez une premiere ligne pour commencer.</td>
            </tr>
            <tr v-for="item in filteredPortfolio" :key="item.id">
              <td>
                <strong>{{ item.symbol }}</strong>
                <div class="muted">{{ item.label }}</div>
              </td>
              <td>{{ item.quantity }}</td>
              <td>{{ appCtx.formatCurrency(item.averagePrice) }}</td>
              <td>{{ appCtx.formatCurrency(item.currentPrice || item.averagePrice) }}</td>
              <td>{{ appCtx.formatCurrency(item.estimatedValue) }}</td>
              <td>
                <span :class="[(item.currentPrice || 0) * item.quantity - item.averagePrice * item.quantity >= 0 ? 'pl-pos' : 'pl-neg']">{{ appCtx.formatCurrency((item.currentPrice || 0) * item.quantity - item.averagePrice * item.quantity) }}</span>
              </td>
              <td><button class="ghost" @click="appCtx.deletePosition(item.id)">Supprimer</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="table-card">
        <div class="table-header">
          <h3>Transactions recentes</h3>
          <div class="header-actions">
            <span>{{ appCtx.transactions.value.length }} entrees</span>
            <input v-model="txFilter" placeholder="Filtrer symbole/type">
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Actif</th>
              <th>Quantite</th>
              <th>Prix unitaire</th>
              <th>Montant</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredTransactions.length === 0">
              <td colspan="6" class="empty">Aucune transaction pour le moment.</td>
            </tr>
            <tr v-for="tx in filteredTransactions" :key="tx.id">
              <td>{{ new Date(tx.createdAt).toLocaleString('fr-FR') }}</td>
              <td><span :class="['pill', tx.side === 'buy' ? 'buy' : 'sell']">{{ tx.side }}</span></td>
              <td>{{ tx.symbol }}</td>
              <td>{{ tx.quantity }}</td>
              <td>{{ appCtx.formatCurrency(tx.price) }}</td>
              <td>{{ appCtx.formatCurrency(tx.price * tx.quantity) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>

