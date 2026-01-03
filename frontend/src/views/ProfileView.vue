<script setup>
import { inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const appCtx = inject('appCtx');
const router = useRouter();

const profileForm = ref({
  displayName: '',
  firstName: '',
  lastName: '',
  birthDate: '',
  currentPassword: '',
  newPassword: '',
});

onMounted(() => {
  if (!appCtx.isAuth.value) {
    router.push('/');
    return;
  }
  profileForm.value.displayName = appCtx.user.value?.displayName || '';
  profileForm.value.firstName = appCtx.user.value?.firstName || '';
  profileForm.value.lastName = appCtx.user.value?.lastName || '';
  profileForm.value.birthDate = appCtx.user.value?.birthDate ? appCtx.user.value.birthDate.split('T')[0] : '';
});

const submitProfile = async () => {
  const payload = {};
  if (profileForm.value.displayName) {
    payload.displayName = profileForm.value.displayName;
  }
  payload.firstName = profileForm.value.firstName;
  payload.lastName = profileForm.value.lastName;
  payload.birthDate = profileForm.value.birthDate;
  if (profileForm.value.newPassword) {
    payload.currentPassword = profileForm.value.currentPassword;
    payload.newPassword = profileForm.value.newPassword;
  }
  await appCtx.updateProfile(payload);
  profileForm.value.currentPassword = '';
  profileForm.value.newPassword = '';
};
</script>

<template>
  <main>
    <section class="hero">
      <div>
        <p class="eyebrow">Profil</p>
        <h1>Gerer votre identite et securite.</h1>
        <p class="subhead">
          Mettez a jour votre Pseudo et changez votre mot de passe. Vos sessions restent securisees cote
          serveur.
        </p>
      </div>
      <div class="cta-panel">
        <p class="info">Connecte en tant que {{ appCtx.user.value?.email }}</p>
      </div>
    </section>

    <section v-if="appCtx.error.value || appCtx.message.value" class="feedback">
      <p v-if="appCtx.error.value" class="error">{{ appCtx.error.value }}</p>
      <p v-if="appCtx.message.value" class="success">{{ appCtx.message.value }}</p>
    </section>

    <section class="forms-grid">
      <div class="form-card">
        <h3>Informations</h3>
        <form @submit.prevent="submitProfile">
          <label>Pseudo
            <input v-model="profileForm.displayName" required>
          </label>
          <label>Nom
            <input v-model="profileForm.lastName" placeholder="Nom">
          </label>
          <label>Prenom
            <input v-model="profileForm.firstName" placeholder="Prenom">
          </label>
          <label>Date de naissance
            <input type="date" v-model="profileForm.birthDate">
          </label>
          <label>Email (lecture seule)
            <input :value="appCtx.user.value?.email" readonly>
          </label>
          <hr>
          <label>Mot de passe actuel
            <input type="password" v-model="profileForm.currentPassword" placeholder="Requis pour changer le mot de passe">
          </label>
          <label>Nouveau mot de passe
            <input type="password" v-model="profileForm.newPassword" placeholder="8 caracteres minimum">
          </label>
          <button type="submit">Mettre a jour</button>
        </form>
      </div>
    </section>
  </main>
</template>
