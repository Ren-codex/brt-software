<script>
import { layoutComputed } from "@/Shared/State/helpers";
import Vertical from "./Vertical.vue";
import Horizontal from "./Horizontal.vue";
import TwoColumns from "./Twocolumn.vue";
export default {
    components: {
        Vertical,
        Horizontal,
        TwoColumns,
    },
    props: { 
        surveyQuestions: Array
    },
    data() {
        return {
            
        };
    },

    computed: {
        ...layoutComputed,
        message() {
            return (this.$page.props.flash.message) ?  true : false;
        }
    },
    methods: {
        check(){
            this.$page.props.flash = {};
            this.message = false;
        },

    }
};
</script>

<template>
    <div>
        <Vertical v-if="layoutType === 'vertical' || layoutType === 'semibox'" :layout="layoutType">
            <slot />
        </Vertical>

        <Horizontal v-if="layoutType === 'horizontal'" :layout="layoutType">
            <slot />
        </Horizontal>

        <TwoColumns v-if="layoutType === 'twocolumn'" :layout="layoutType">
            <slot />
        </TwoColumns>
    </div>
    <div v-if="message" class="flash-modal-overlay" @click.self="check()">
        <div class="flash-modal">
            <button class="flash-modal-close" @click="check()">
                <i class="ri-close-line"></i>
            </button>
            <div class="flash-modal-content">
                <div class="flash-icon-wrapper" :class="$page.props.flash.status ? 'success' : 'error'">
                    <i v-if="$page.props.flash.status" class="ri-checkbox-circle-fill"></i>
                    <i v-else class="ri-close-circle-fill"></i>
                </div>
                <h4 class="flash-title">{{ $page.props.flash.message }}</h4>
                <p v-if="$page.props.flash.info" class="flash-info">{{ $page.props.flash.info }}</p>
                <button class="flash-btn" @click="check()">
                    <i class="ri-check-line me-2"></i>
                    Got it
                </button>
            </div>
            <div class="flash-footer">
                <p class="flash-footer-text">
                    Need help? Contact 
                    <a href="javascript:void(0)" class="flash-link">Administrator</a>
                </p>
            </div>
        </div>
    </div>
</template>
