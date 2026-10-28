<script setup>
import { ref } from 'vue';
defineProps({ action: { type: String, default: '/recherche' } });
const q = ref('');
</script>

<template>
  <form :action="action" method="get" role="search">
    <label :for="'q-island'">{{ $t('search.label') }}</label>
    <input id="q-island" v-model="q" name="q" type="search" :placeholder="$t('search.placeholder')">
    <button type="submit">{{ $t('search.label') }}</button>
  </form>
</template>
