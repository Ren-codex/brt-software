<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Status' : 'Create New Status' }}</h2>
                <button class="close-btn" @click="hide" aria-label="Close modal">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow: auto;">
                <form @submit.prevent="submit" id="statusForm">
                    <!-- Name Field -->
                    <div class="form-group">
                        <label for="name" class="form-label">Status Name *</label>
                        <div class="input-wrapper">
                            <i class="ri-bookmark-line input-icon"></i>
                            <input 
                                type="text" 
                                id="name" 
                                v-model="form.name" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="e.g., Pending Approval"
                                @input="handleInput('name')"
                                required
                                aria-describedby="name-help"
                                aria-invalid="form.errors.name ? 'true' : 'false'"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.name">{{ form.errors.name }}</span>
                        <div id="name-help" class="form-help-text">
                            Use clear, descriptive names. Maximum 50 characters.
                        </div>
                    </div>

                    <!-- Description Field -->
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <div class="input-wrapper">
                            <i class="ri-file-text-line input-icon textarea-icon"></i>
                            <textarea 
                                id="description" 
                                v-model="form.description" 
                                class="form-control textarea-control"
                                :class="{ 'input-error': form.errors.description }"
                                placeholder="Optional: Describe when this status should be used"
                                rows="3"
                                @input="handleInput('description')"
                                maxlength="255"
                                aria-describedby="description-help"
                            ></textarea>
                        </div>
                        <span class="error-message" v-if="form.errors.description">{{ form.errors.description }}</span>
                        <div class="character-count">
                            {{ form.description ? form.description.length : 0 }}/255 characters
                        </div>
                    </div>

                    <!-- Color Presets -->
                    <div class="form-group">
                        <label class="form-label">Quick Color Presets</label>
                        <div class="color-presets">
                            <button 
                                type="button" 
                                v-for="preset in colorPresets" 
                                :key="preset.name"
                                class="color-preset"
                                @click="applyPreset(preset)"
                                :title="`Apply ${preset.name} colors`"
                                :class="{ active: isPresetActive(preset) }"
                            >
                                <span class="preset-dot" :style="{ backgroundColor: preset.bg }"></span>
                                <span class="preset-text" :style="{ color: preset.text }">{{ preset.name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Color Inputs -->
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="text_color" class="form-label">Text Color *</label>
                            <div class="input-wrapper">
                                <i class="ri-palette-line input-icon"></i>
                                <ColorInput 
                                    v-model="form.text_color" 
                                    class="form-control color-input"
                                    :class="{ 'input-error': form.errors.text_color || contrastWarning }"
                                    @input="handleInput('text_color')" 
                                    required
                                    aria-describedby="text-color-help"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.text_color">{{ form.errors.text_color }}</span>
                            <div id="text-color-help" class="form-help-text">
                                Hex color code (e.g., #FFFFFF)
                            </div>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="bg_color" class="form-label">Background Color *</label>
                            <div class="input-wrapper">
                                <i class="ri-paint-fill input-icon"></i>
                                <ColorInput 
                                    v-model="form.bg_color" 
                                    class="form-control color-input"
                                    :class="{ 'input-error': form.errors.bg_color || contrastWarning }"
                                    @input="handleInput('bg_color')" 
                                    required
                                    aria-describedby="bg-color-help"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.bg_color">{{ form.errors.bg_color }}</span>
                            <div id="bg-color-help" class="form-help-text">
                                Hex color code (e.g., #007BFF)
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contrast Warning -->
                    <div class="warning-alert" v-if="contrastWarning">
                        <i class="ri-alert-line"></i>
                        <span>Poor color contrast detected. Text may be hard to read.</span>
                    </div>

                    <!-- Live Preview -->
                    <div class="form-group">
                        <label class="form-label">Live Preview</label>
                        <div class="preview-container">
                            <div class="preview-examples">
                                <span class="preview-label">Badge:</span>
                                <span 
                                    class="status-preview" 
                                    :style="{
                                        color: form.text_color || '#000000',
                                        backgroundColor: form.bg_color || '#ffffff',
                                        borderColor: form.bg_color ? form.bg_color + '40' : '#cccccc'
                                    }"
                                >
                                    {{ form.name || 'Status Preview' }}
                                </span>
                            </div>
                            <div class="preview-examples">
                                <span class="preview-label">In Table:</span>
                                <div class="table-preview">
                                    <table>
                                        <tbody>
                                            <tr>
                                                <td>Sample Item</td>
                                                <td>
                                                    <span 
                                                        class="status-badge" 
                                                        :style="{
                                                            color: form.text_color || '#000000',
                                                            backgroundColor: form.bg_color || '#ffffff',
                                                            borderColor: form.bg_color ? form.bg_color + '40' : '#cccccc'
                                                        }"
                                                    >
                                                        {{ form.name || 'Status' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="preview-description">
                                This is how the status will appear throughout the application.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Success Message -->
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Status {{ editable ? 'updated' : 'created' }} successfully!</span>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide" :disabled="form.processing">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || !form.name || !form.text_color || !form.bg_color">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : (editable ? 'Update Status' : 'Create Status') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import ColorInput from '@/Shared/Components/Forms/ColorInput.vue';
import Swal from 'sweetalert2';

export default {
    components: { ColorInput },
    props: ['dropdowns'],
    data() {
        return {
            form: useForm({
                id: null,
                name: null,
                description: null,
                text_color: '#000000',
                bg_color: '#ffffff',
                option: 'lists'
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            colorPresets: [
                { name: 'Active', text: '#155724', bg: '#d4edda' },
                { name: 'Pending', text: '#856404', bg: '#fff3cd' },
                { name: 'Approved', text: '#0c5460', bg: '#d1ecf1' },
                { name: 'Rejected', text: '#721c24', bg: '#f8d7da' },
                { name: 'Completed', text: '#155724', bg: '#d4edda' },
                { name: 'Cancelled', text: '#721c24', bg: '#f8d7da' },
                { name: 'Draft', text: '#383d41', bg: '#e2e3e5' },
                { name: 'Processing', text: '#004085', bg: '#cce5ff' },
            ]
        }
    },
    computed: {
        contrastWarning() {
            return !this.validateColorContrast();
        }
    },
    methods: {
        show() {
            this.form.defaults({
                id: null,
                name: null,
                description: null,
                text_color: '#000000',
                bg_color: '#ffffff',
                option: '',
            }).reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            
            // Focus first input when modal opens
            this.$nextTick(() => {
                const nameInput = document.getElementById('name');
                if (nameInput) nameInput.focus();
            });
        },
        
        edit(data) {
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.description = data.description;
            this.form.text_color = data.text_color || '#000000';
            this.form.bg_color = data.bg_color || '#ffffff';
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
            
            // Focus name input when editing
            this.$nextTick(() => {
                const nameInput = document.getElementById('name');
                if (nameInput) nameInput.focus();
            });
        },
        
        submit() {
            // Validate colors
            if (!this.validateColor(this.form.text_color) || !this.validateColor(this.form.bg_color)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Colors',
                    text: 'Please enter valid hex color codes (e.g., #FF0000)',
                });
                return;
            }
            
            // Warn about poor contrast
            if (this.contrastWarning) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Poor Color Contrast',
                    html: 'The text may be hard to read on this background.<br><br>Do you want to continue?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Save Anyway',
                    cancelButtonText: 'Adjust Colors',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.continueSubmit();
                    }
                });
            } else {
                this.continueSubmit();
            }
        },
        
        continueSubmit() {
            const method = this.editable ? 'put' : 'post';
            const url = this.editable 
                ? `/libraries/statuses/${this.form.id}`
                : '/libraries/statuses';
                
            this.form[method](url, {
                preserveScroll: true,
                onSuccess: (response) => {
                    const action = this.editable ? 'updated' : 'created';
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Status ${action} successfully!`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    this.$emit('add', true);
                    this.hide();
                },
                onError: (errors) => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Please check the form for errors.',
                    });
                }
            });
        },
        
        handleInput(field) {
            this.form.errors[field] = false;
        },
        
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },
        
        applyPreset(preset) {
            this.form.text_color = preset.text;
            this.form.bg_color = preset.bg;
        },
        
        isPresetActive(preset) {
            return this.form.text_color === preset.text && this.form.bg_color === preset.bg;
        },
        
        validateColor(color) {
            if (!color) return false;
            const hexRegex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
            return hexRegex.test(color);
        },
        
        hexToRgb(hex) {
            if (!hex) return { r: 0, g: 0, b: 0 };
            
            // Remove # if present
            hex = hex.replace('#', '');
            
            // Convert 3-digit hex to 6-digit
            if (hex.length === 3) {
                hex = hex.split('').map(c => c + c).join('');
            }
            
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            
            return { r, g, b };
        },
        
        calculateLuminance(rgb) {
            const { r, g, b } = rgb;
            
            const rsrgb = r / 255;
            const gsrgb = g / 255;
            const bsrgb = b / 255;
            
            const R = rsrgb <= 0.03928 ? rsrgb / 12.92 : Math.pow((rsrgb + 0.055) / 1.055, 2.4);
            const G = gsrgb <= 0.03928 ? gsrgb / 12.92 : Math.pow((gsrgb + 0.055) / 1.055, 2.4);
            const B = bsrgb <= 0.03928 ? bsrgb / 12.92 : Math.pow((bsrgb + 0.055) / 1.055, 2.4);
            
            return 0.2126 * R + 0.7152 * G + 0.0722 * B;
        },
        
        validateColorContrast() {
            if (!this.form.text_color || !this.form.bg_color) return true;
            
            const textRgb = this.hexToRgb(this.form.text_color);
            const bgRgb = this.hexToRgb(this.form.bg_color);
            
            const textLum = this.calculateLuminance(textRgb);
            const bgLum = this.calculateLuminance(bgRgb);
            
            // Calculate contrast ratio
            const lighter = Math.max(textLum, bgLum);
            const darker = Math.min(textLum, bgLum);
            const contrast = (lighter + 0.05) / (darker + 0.05);
            
            // Minimum contrast ratio for normal text is 4.5:1
            return contrast >= 4.5;
        }
    }
}
</script>

<style scoped>
/* Add these styles to your existing CSS */
.warning-alert {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid #ffeaa7;
}

.warning-alert i {
    font-size: 1.2rem;
}

.character-count {
    font-size: 12px;
    color: #6c757d;
    text-align: right;
    margin-top: 4px;
}

.table-preview {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 10px;
    margin-top: 5px;
}

.table-preview table {
    width: 100%;
    border-collapse: collapse;
}

.table-preview td {
    padding: 8px;
    border-bottom: 1px solid #f3f4f6;
}

.preview-examples {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.preview-label {
    font-weight: 600;
    color: #495057;
    font-size: 14px;
    min-width: 80px;
}

.status-preview {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid;
    transition: all 0.3s ease;
    min-width: 120px;
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid;
}

.preview-container {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
}

.preview-description {
    font-size: 12px;
    color: #6c757d;
    margin-top: 8px;
}

.color-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.color-preset {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.color-preset:hover {
    border-color: #2e8b57;
    background: #f8f9fa;
}

.color-preset.active {
    border-color: #2e8b57;
    background: rgba(46, 139, 87, 0.1);
}

.preset-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.preset-text {
    font-size: 12px;
    font-weight: 500;
}

/* Color Input Specific Styles */
.color-input {
    padding-left: 3rem !important;
}

.color-input::placeholder {
    color: #6c757d;
    opacity: 0.7;
}

/* Responsive Design */
@media (max-width: 768px) {
    .preview-examples {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .preview-label {
        min-width: auto;
    }
    
    .color-presets {
        justify-content: center;
    }
}
</style>