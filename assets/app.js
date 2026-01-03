import './stimulus_bootstrap.js';
import './styles/app.css';
import { createApp, ref, computed, onMounted } from 'vue';

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

const App = {
    setup() {
        const user = ref(null);
        const authMode = ref('login');
        const authForm = ref({ email: '', password: '', displayName: '' });
        const loading = ref(false);
        const message = ref('');
        const error = ref('');

        const portfolio = ref([]);
        const totals = ref({ invested: 0, estimatedValue: 0 });
        const transactions = ref([]);

        const newPosition = ref({ symbol: '', label: '', quantity: 0, averagePrice: 0 });
        const newTransaction = ref({ symbol: '', side: 'buy', quantity: 0, price: 0 });

        const isAuth = computed(() => !!user.value);

        const resetMessages = () => {
            message.value = '';
            error.value = '';
        };

        const fetchMe = async () => {
            try {
                const data = await api('/api/me');
                user.value = data;
                await Promise.all([loadPortfolio(), loadTransactions()]);
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
            }
            try {
                const data = await api(`/api/${authMode.value}`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                user.value = data.user;
                message.value = data.message;
                await Promise.all([loadPortfolio(), loadTransactions()]);
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
        };

        const loadPortfolio = async () => {
            const data = await api('/api/portfolio');
            portfolio.value = data.items;
            totals.value = data.totals;
        };

        const loadTransactions = async () => {
            const data = await api('/api/transactions');
            transactions.value = data.items;
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

        const formatCurrency = (value) => new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            maximumFractionDigits: 2,
        }).format(value);

        onMounted(() => {
            fetchMe();
        });

        return {
            authMode,
            authForm,
            loading,
            message,
            error,
            user,
            isAuth,
            portfolio,
            totals,
            transactions,
            newPosition,
            newTransaction,
            submitAuth,
            logout,
            addPosition,
            addTransaction,
            deletePosition,
            formatCurrency,
        };
    },
    template: `
    <section class="hero">
        <div>
            <p class="eyebrow">Crypto Tracker</p>
            <h1>Suivez vos actifs avec une interface claire et sécurisée.</h1>
            <p class="subhead">Enregistrez vos positions, gardez un historique des transactions et visualisez la valeur globale de votre portefeuille.</p>
        </div>
        <div class="cta-panel" v-if="!isAuth">
            <div class="tabs">
                <button :class="{active: authMode === 'login'}" @click="authMode = 'login'">Connexion</button>
                <button :class="{active: authMode === 'register'}" @click="authMode = 'register'">Inscription</button>
            </div>
            <form @submit.prevent="submitAuth">
                <label>Email
                    <input type="email" v-model="authForm.email" required>
                </label>
                <label>Mot de passe
                    <input type="password" v-model="authForm.password" required>
                </label>
                <label v-if="authMode === 'register'">Nom d'affichage
                    <input type="text" v-model="authForm.displayName" placeholder="ex: Alice">
                </label>
                <button type="submit" :disabled="loading">{{ authMode === 'login' ? 'Connexion' : 'Créer mon compte' }}</button>
            </form>
            <p class="info">Les sessions sont sécurisées et stockées côté serveur (Symfony).</p>
        </div>
        <div class="cta-panel" v-else>
            <p class="eyebrow">Bienvenue</p>
            <h3>{{ user.displayName }}</h3>
            <p>{{ user.email }}</p>
            <button class="secondary" @click="logout">Se déconnecter</button>
        </div>
    </section>

    <section v-if="error || message" class="feedback">
        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="message" class="success">{{ message }}</p>
    </section>

    <section v-if="isAuth" class="dashboard">
        <div class="stat-card">
            <p>Total investi</p>
            <h2>{{ formatCurrency(totals.invested || 0) }}</h2>
        </div>
        <div class="stat-card">
            <p>Valeur estimée</p>
            <h2>{{ formatCurrency(totals.estimatedValue || 0) }}</h2>
        </div>
    </section>

    <section v-if="isAuth" class="forms-grid">
        <div class="form-card">
            <h3>Ajouter une position</h3>
            <form @submit.prevent="addPosition">
                <label>Symbole
                    <input v-model="newPosition.symbol" placeholder="BTC" required>
                </label>
                <label>Libellé
                    <input v-model="newPosition.label" placeholder="Bitcoin">
                </label>
                <label>Quantité
                    <input type="number" step="0.00000001" v-model.number="newPosition.quantity" required>
                </label>
                <label>Prix moyen (€)
                    <input type="number" step="0.01" v-model.number="newPosition.averagePrice" required>
                </label>
                <button type="submit">Enregistrer</button>
            </form>
        </div>
        <div class="form-card">
            <h3>Enregistrer une transaction</h3>
            <form @submit.prevent="addTransaction">
                <label>Symbole
                    <input v-model="newTransaction.symbol" placeholder="ETH" required>
                </label>
                <label>Type
                    <select v-model="newTransaction.side">
                        <option value="buy">Achat</option>
                        <option value="sell">Vente</option>
                    </select>
                </label>
                <label>Quantité
                    <input type="number" step="0.00000001" v-model.number="newTransaction.quantity" required>
                </label>
                <label>Prix (€)
                    <input type="number" step="0.01" v-model.number="newTransaction.price" required>
                </label>
                <button type="submit">Ajouter</button>
            </form>
        </div>
    </section>

    <section v-if="isAuth" class="tables">
        <div class="table-card">
            <div class="table-header">
                <h3>Portefeuille</h3>
                <span>{{ portfolio.length }} lignes</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Actif</th>
                        <th>Quantité</th>
                        <th>Prix moyen</th>
                        <th>Valeur estimée</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="portfolio.length === 0">
                        <td colspan="5" class="empty">Ajoutez une première ligne pour commencer.</td>
                    </tr>
                    <tr v-for="item in portfolio" :key="item.id">
                        <td>
                            <strong>{{ item.symbol }}</strong>
                            <div class="muted">{{ item.label }}</div>
                        </td>
                        <td>{{ item.quantity }}</td>
                        <td>{{ formatCurrency(item.averagePrice) }}</td>
                        <td>{{ formatCurrency(item.estimatedValue) }}</td>
                        <td><button class="ghost" @click="deletePosition(item.id)">Supprimer</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-card">
            <div class="table-header">
                <h3>Transactions récentes</h3>
                <span>{{ transactions.length }} entrées</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Actif</th>
                        <th>Quantité</th>
                        <th>Prix</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="transactions.length === 0">
                        <td colspan="5" class="empty">Aucune transaction pour le moment.</td>
                    </tr>
                    <tr v-for="tx in transactions" :key="tx.id">
                        <td>{{ new Date(tx.createdAt).toLocaleString('fr-FR') }}</td>
                        <td><span :class="['pill', tx.side === 'buy' ? 'buy' : 'sell']">{{ tx.side }}</span></td>
                        <td>{{ tx.symbol }}</td>
                        <td>{{ tx.quantity }}</td>
                        <td>{{ formatCurrency(tx.price) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    `,
};

const root = document.getElementById('app');
if (root) {
    createApp(App).mount(root);
}
