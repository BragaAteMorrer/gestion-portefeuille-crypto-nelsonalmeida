<script setup>
import { inject, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const appCtx = inject('appCtx');
const router = useRouter();

onMounted(() => {
  if (!appCtx.isAdmin.value) {
    router.push('/');
  } else {
    appCtx.loadAdminData();
  }
});
</script>

<template>
  <main>
    <section class="hero">
      <div>
        <p class="eyebrow">Admin</p>
        <h1>Gestion des comptes et supervision.</h1>
      </div>
      <div class="cta-panel">
        <p class="info">Accès réservé aux administrateurs.</p>
      </div>
    </section>

    <section v-if="appCtx.error.value || appCtx.message.value" class="feedback">
      <p v-if="appCtx.error.value" class="error">{{ appCtx.error.value }}</p>
      <p v-if="appCtx.message.value" class="success">{{ appCtx.message.value }}</p>
    </section>

    <section v-if="appCtx.isAdmin.value" class="admin">
      <div class="table-card">
        <div class="table-header">
          <h3>Administration</h3>
          <span>Contrôle des comptes</span>
        </div>
        <div class="admin-stats" v-if="appCtx.adminStats.value">
          <div class="stat-card">
            <p>Utilisateurs</p>
            <h3>{{ appCtx.adminStats.value.users }}</h3>
          </div>
          <div class="stat-card">
            <p>Lignes de portefeuille</p>
            <h3>{{ appCtx.adminStats.value.portfolioEntries }}</h3>
          </div>
          <div class="stat-card">
            <p>Transactions</p>
            <h3>{{ appCtx.adminStats.value.transactions }}</h3>
          </div>
        </div>
        
<table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Pseudo</th>
              <th>Nom</th>
              <th>Prenom</th>
              <th>Naissance</th>
              <th>Roles</th>
              <th>Portefeuille</th>
              <th>Tx</th>
              <th>Voir</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="appCtx.adminUsers.value.length === 0">
              <td colspan="12" class="empty">Aucun utilisateur pour le moment.</td>
            </tr>
            <tr v-for="u in appCtx.adminUsers.value" :key="u.id">
              <td>{{ u.id }}</td>
              <td>{{ u.email }}</td>
              <td>{{ u.displayName }}</td>
              <td>{{ u.lastName || '-' }}</td>
              <td>{{ u.firstName || '-' }}</td>
              <td>{{ u.birthDate ? new Date(u.birthDate).toLocaleDateString('fr-FR') : '-' }}</td>
              <td>{{ u.roles.join(', ') }}</td>
              <td>{{ u.portfolioCount }}</td>
              <td>{{ u.transactionCount }}</td>
              <td>
                <button class="ghost" @click="appCtx.loadAdminUserData(u.id)">Ouvrir</button>
              </td>
              <td>{{ u.suspended ? 'Suspendu' : 'Actif' }}</td>
              <td class="admin-actions">
                <button class="ghost" @click="appCtx.toggleSuspend(u.id, !u.suspended)">
                  {{ u.suspended ? 'Reactiver' : 'Suspendre' }}
                </button>
                <button class="ghost" @click="appCtx.toggleAdmin(u.id, !u.roles.includes('ROLE_ADMIN'))">
                  {{ u.roles.includes('ROLE_ADMIN') ? 'Retirer admin' : 'Rendre admin' }}
                </button>
                <button class="ghost danger" @click="appCtx.deleteUserAdmin(u.id)" :disabled="u.id === appCtx.user.value?.id">
                  Supprimer
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="table-card" v-if="appCtx.adminPreviewUser.value">
        <div class="table-header">
          <h3>Portefeuille de {{ appCtx.adminPreviewUser.value.displayName || appCtx.adminPreviewUser.value.email }}</h3>
          <div class="header-actions">
            <span>ID {{ appCtx.adminPreviewUser.value.id }}</span>
            <button class="ghost" @click="() => { appCtx.adminPreviewUser.value = null; appCtx.adminPreviewPortfolio.value = []; appCtx.adminPreviewTransactions.value = []; }">Fermer</button>
          </div>
        </div>
        
        <div class="forms-grid" style="padding: 0 16px 16px;">
          <div class="form-card">
            <h4>Informations utilisateur</h4>
            <p><strong>Pseudo :</strong> {{ appCtx.adminPreviewUser.value.displayName }}</p>
            <p><strong>Nom :</strong> {{ appCtx.adminPreviewUser.value.lastName || '-' }}</p>
            <p><strong>Prenom :</strong> {{ appCtx.adminPreviewUser.value.firstName || '-' }}</p>
            <p><strong>Date de naissance :</strong> {{ appCtx.adminPreviewUser.value.birthDate ? new Date(appCtx.adminPreviewUser.value.birthDate).toLocaleDateString('fr-FR') : '-' }}</p>
          </div>
        </div>
<table>
          <thead>
            <tr>
              <th>Actif</th>
              <th>Quantité</th>
              <th>Prix moyen</th>
              <th>Cours actuel</th>
              <th>Valeur estimée</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="appCtx.adminPreviewPortfolio.value.length === 0">
              <td colspan="5" class="empty">Aucune ligne pour cet utilisateur.</td>
            </tr>
            <tr v-for="item in appCtx.adminPreviewPortfolio.value" :key="item.id">
              <td>
                <strong>{{ item.symbol }}</strong>
                <div class="muted">{{ item.label }}</div>
              </td>
              <td>{{ item.quantity }}</td>
              <td>{{ appCtx.formatCurrency(item.averagePrice) }}</td>
              <td>{{ appCtx.formatCurrency(item.currentPrice || item.averagePrice) }}</td>
              <td>{{ appCtx.formatCurrency(item.estimatedValue) }}</td>
            </tr>
          </tbody>
        </table>

        <div class="table-header" style="margin-top: 1rem;">
          <h4>Transactions</h4>
          <span>{{ appCtx.adminPreviewTransactions.value.length }} entrées</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Symbole</th>
              <th>Quantité</th>
              <th>Prix unitaire</th>
              <th>Montant</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="appCtx.adminPreviewTransactions.value.length === 0">
              <td colspan="6" class="empty">Aucune transaction.</td>
            </tr>
            <tr v-for="tx in appCtx.adminPreviewTransactions.value" :key="tx.id">
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
