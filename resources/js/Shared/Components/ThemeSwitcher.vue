<template>
    <!-- Every signed-in user gets the button. The theme each person saves is
         their own: it changes their screen and nobody else's. -->
    <template v-if="canChangeTheme">
        <button type="button" class="theme-fab" :class="{ 'is-open': open }"
            :title="open ? 'Close theme settings' : 'My theme'" aria-label="My theme"
            @click="toggle">
            <i :class="open ? 'ri-close-line' : 'ri-palette-line'"></i>
        </button>

        <div v-if="open" class="theme-panel" role="dialog" aria-label="My theme">
            <div class="theme-panel-head">
                <div>
                    <strong>My theme</strong>
                    <span>Changes the colours on your screen only</span>
                </div>
            </div>

            <div class="theme-panel-body">
                <div class="theme-label">Ready-made themes</div>
                <div class="theme-swatches">
                    <button v-for="preset in presets" :key="preset.name" type="button" class="theme-swatch"
                        :class="{ active: isSelected(preset.color) }" :title="preset.name"
                        @click="pick(preset.color)">
                        <span class="theme-swatch-dot" :style="{ background: preset.swatch }">
                            <i v-if="isSelected(preset.color)" class="ri-check-line"></i>
                        </span>
                        <span class="theme-swatch-name">{{ preset.name }}</span>
                    </button>
                </div>

                <div class="theme-label">Or choose your own colour</div>
                <label class="theme-custom">
                    <input type="color" :value="draft || defaultColor" @input="pick($event.target.value)">
                    <span>
                        <strong>{{ draft || 'Default green' }}</strong>
                        <small>Very light or very dark colours are adjusted so text stays readable.</small>
                    </span>
                </label>

                <!-- What the choice looks like, using the live theme variables. -->
                <div class="theme-label">Preview</div>
                <div class="theme-preview">
                    <div class="theme-preview-header">
                        <span class="theme-preview-icon"><i class="ri-shopping-cart-line"></i></span>
                        <div>
                            <div class="theme-preview-title">Sales Orders</div>
                            <div class="theme-preview-sub">Manage sales orders</div>
                        </div>
                    </div>
                    <div class="theme-preview-body">
                        <span class="theme-preview-btn">Button</span>
                        <span class="theme-preview-pill">Selected</span>
                        <a href="#" class="theme-preview-link" @click.prevent>A link</a>
                    </div>
                </div>

                <p v-if="isDirty" class="theme-note">
                    <i class="ri-eye-line"></i> This is a preview. Save to keep it; it follows you to any computer you sign in on.
                </p>
            </div>

            <div class="theme-panel-foot">
                <button type="button" class="theme-btn" :disabled="saving || (!draft && !saved)" @click="pick(null)">
                    Default
                </button>
                <button type="button" class="theme-btn" :disabled="saving || !isDirty" @click="cancel">Cancel</button>
                <button type="button" class="theme-btn theme-btn-primary" :disabled="saving || !isDirty" @click="save">
                    {{ saving ? 'Saving…' : 'Save my theme' }}
                </button>
            </div>
        </div>
    </template>
</template>

<script>
import { THEME_PRESETS, applyTheme, isHexColor } from '@/Shared/theme.js';

/**
 * The floating theme button, and the thing that keeps the screen on the
 * signed-in user's saved theme.
 *
 * Each user has their own colour, which arrives with every page as
 * `$page.props.theme.primary` and is applied here whenever it changes. It is
 * stored on their account, so it follows them between computers and never
 * shows for anyone else.
 *
 * Picking a colour in the panel is a preview. Nothing is kept until Save, and
 * Cancel (or closing the panel) puts the saved theme back.
 */
export default {
    data() {
        return {
            open: false,
            saving: false,
            presets: THEME_PRESETS,
            defaultColor: '#3d8d7a',
            // The colour being previewed; null is the default green.
            draft: null,
        };
    },
    computed: {
        /** This user's saved colour, as the server last told us. */
        saved() {
            const color = this.$page?.props?.theme?.primary;
            return isHexColor(color) ? color.toLowerCase() : null;
        },
        /** Anyone signed in may choose their own theme. */
        canChangeTheme() {
            return !!this.$page?.props?.user?.data?.id;
        },
        isDirty() {
            return this.draft !== this.saved;
        },
    },
    watch: {
        saved: {
            immediate: true,
            handler(color) {
                // Not while previewing: an unrelated page change must not yank
                // the preview out from under the person choosing.
                if (this.open && this.isDirty) return;

                this.draft = color;
                applyTheme(color);
            },
        },
    },
    methods: {
        toggle() {
            if (this.open) {
                this.cancel();
                this.open = false;
            } else {
                this.draft = this.saved;
                this.open = true;
            }
        },
        isSelected(color) {
            return (color ? color.toLowerCase() : null) === this.draft;
        },
        /** Preview a colour on this screen only. */
        pick(color) {
            this.draft = isHexColor(color) ? color.toLowerCase() : null;
            applyTheme(this.draft);
        },
        cancel() {
            this.draft = this.saved;
            applyTheme(this.saved);
        },
        save() {
            this.saving = true;

            axios.post('/system-theme', { primary: this.draft })
                .then(res => {
                    // Keep the shared value in step without waiting for the
                    // next page visit to deliver it.
                    if (this.$page?.props) {
                        this.$page.props.theme = { primary: res.data.primary };
                    }
                    this.$toast?.success('Your theme is saved.');
                    this.open = false;
                })
                .catch(err => {
                    this.$toast?.error(err?.response?.data?.message || 'Could not save the theme.');
                })
                .finally(() => { this.saving = false; });
        },
    },
};
</script>

<style scoped>
/* On the right edge, a quarter of the way down. Not halfway: the Inventory and
   Sales pages keep their "Quick Stats" tab at the middle of this edge, and the
   two sat on top of each other. Up here it is also clear of the notification
   bell above and the minimised-form bar and back-to-top button below. */
.theme-fab {
    position: fixed;
    right: 0;
    top: 26%;
    transform: translateY(-50%);
    z-index: 1050;
    width: 42px;
    height: 46px;
    border: none;
    border-radius: 12px 0 0 12px;
    background: var(--brt-primary);
    color: #fff;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: -4px 4px 16px rgba(0, 0, 0, 0.22);
    transition: width 0.15s ease, background-color 0.15s ease;
}
.theme-fab:hover,
.theme-fab.is-open {
    width: 50px;
    background: var(--brt-primary-hover);
}

.theme-panel {
    position: fixed;
    right: 62px;
    /* Opens beside the button, but never above the top of the screen. */
    top: max(1rem, calc(26% - 23px));
    z-index: 1050;
    width: 320px;
    max-width: calc(100vw - 80px);
    max-height: calc(100vh - max(2rem, 26%));
    display: flex;
    flex-direction: column;
    border: 1px solid var(--brt-border);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.35);
    overflow: hidden;
}

.theme-panel-head {
    padding: 0.85rem 1rem;
    background: linear-gradient(to right, var(--brt-tint-1) 0%, var(--brt-tint-2) 100%);
    border-bottom: 1px solid var(--brt-border);
}
.theme-panel-head strong { display: block; font-size: 0.95rem; color: var(--brt-ink); }
.theme-panel-head span { font-size: 0.75rem; color: var(--brt-muted); }

.theme-panel-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 0.9rem 1rem;
}

.theme-label {
    margin: 0.9rem 0 0.45rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--brt-muted);
}
.theme-label:first-child { margin-top: 0; }

.theme-swatches {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.45rem;
}
.theme-swatch {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem 0.15rem;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
}
.theme-swatch:hover { background: var(--brt-c-f4f6f5); }
.theme-swatch.active { border-color: var(--brt-border); background: var(--brt-tint-2); }
.theme-swatch-dot {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.05rem;
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.55), 0 1px 3px rgba(0, 0, 0, 0.25);
}
.theme-swatch-name { font-size: 0.64rem; color: #4b5563; text-align: center; line-height: 1.2; }

.theme-custom {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin: 0;
    padding: 0.55rem 0.65rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
}
.theme-custom input[type="color"] {
    flex-shrink: 0;
    width: 42px;
    height: 36px;
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
}
.theme-custom strong { display: block; font-size: 0.82rem; color: #1f2937; text-transform: uppercase; }
.theme-custom small { display: block; font-size: 0.68rem; color: #6b7280; line-height: 1.3; }

.theme-preview {
    border: 1px solid var(--brt-border);
    border-radius: 10px;
    overflow: hidden;
}
.theme-preview-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.7rem;
    background: linear-gradient(to right, var(--brt-tint-1) 0%, var(--brt-tint-2) 100%);
    border-bottom: 1px solid var(--brt-border);
}
.theme-preview-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(var(--brt-primary-rgb), 0.12);
    color: var(--brt-primary);
    display: flex;
    align-items: center;
    justify-content: center;
}
.theme-preview-title { font-size: 0.82rem; font-weight: 700; color: var(--brt-ink); }
.theme-preview-sub { font-size: 0.68rem; color: var(--brt-muted); }
.theme-preview-body {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.65rem 0.7rem;
}
.theme-preview-btn {
    padding: 0.3rem 0.75rem;
    border-radius: 8px;
    background: var(--brt-primary);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
}
.theme-preview-pill {
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: rgba(var(--brt-primary-rgb), 0.12);
    color: var(--brt-primary-dark);
    font-size: 0.72rem;
    font-weight: 600;
}
.theme-preview-link { font-size: 0.78rem; font-weight: 600; color: var(--vz-link-color); }

.theme-note {
    display: flex;
    gap: 0.4rem;
    margin: 0.75rem 0 0;
    padding: 0.5rem 0.6rem;
    border-radius: 8px;
    background: #fffbea;
    border: 1px solid #f2e3ab;
    font-size: 0.72rem;
    color: #6b5b0f;
}

.theme-panel-foot {
    display: flex;
    gap: 0.4rem;
    padding: 0.7rem 1rem;
    border-top: 1px solid var(--brt-c-edf0ef);
    background: #fafbfb;
}
.theme-btn {
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--brt-c-d5dbd9);
    border-radius: 8px;
    background: #fff;
    color: #374151;
    font-size: 0.76rem;
    font-weight: 600;
    cursor: pointer;
}
.theme-btn:hover:not(:disabled) { border-color: var(--brt-primary); }
.theme-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.theme-btn-primary {
    margin-left: auto;
    border-color: var(--brt-primary);
    background: var(--brt-primary);
    color: #fff;
}
.theme-btn-primary:hover:not(:disabled) { background: var(--brt-primary-hover); }

@media (max-width: 575.98px) {
    .theme-panel {
        right: 0.5rem;
        left: 0.5rem;
        width: auto;
        max-width: none;
    }
    .theme-fab { top: auto; bottom: 110px; transform: none; }
    .theme-panel { top: 1rem; }
}
</style>
