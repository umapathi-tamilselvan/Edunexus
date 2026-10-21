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
    <LoginCard :title="school.name" subtitle="Enter the email and password your administrator gave you." :submit="signIn" />
</template>
