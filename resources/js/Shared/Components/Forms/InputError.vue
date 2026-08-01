<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message" class="invalid-feedback mt-n1" :style="{ display: message ? 'block' : 'none' }">
        <span class="fs-10">
            {{ message }}
        </span>
    </div>
</template>
