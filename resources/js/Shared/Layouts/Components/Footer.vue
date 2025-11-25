<template>
    <footer class="footer-section">
        <BContainer>
            <BRow class="align-items-center g-0">
                <BCol md="6" class="text-center text-md-start">
                    <p class="footer-text mb-0">
                        &copy; {{ currentYear }} BRT Accounting Software. All rights reserved.
                    </p>
                </BCol>
                <BCol md="6" class="text-center text-md-end">
                    <div class="footer-links">
                        <span class="footer-link">Version 1.0</span>
                        <span class="separator">|</span>
                        <span class="footer-link">11/11/2025</span>
                    </div>
                </BCol>
            </BRow>
        </BContainer>
    </footer>
</template>

<script setup>
import { computed } from 'vue';

// Get current year for footer
const currentYear = computed(() => new Date().getFullYear());
</script>
