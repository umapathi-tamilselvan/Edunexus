<script setup>
import { useRouter } from 'vue-router';
import LoginCard from '../../components/LoginCard.vue';
import { session } from '../session';

const school = window.EduNexus;
const router = useRouter();

async function signIn(credentials) {
    await session.signIn(credentials);
    router.push({ name: 'dashboard' });
}
</script>

<template>
    <LoginCard :title="school.name" subtitle="Sign in to EduNexus" :submit="signIn" />
</template>
