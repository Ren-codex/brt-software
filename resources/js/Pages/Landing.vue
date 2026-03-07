<template>
    <Head title="Premium Rice Business" />
    <div class="brt-landing-page">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
        </div>

        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light fixed-top" :class="{ 'scrolled': isScrolled, 'hidden': hideNav }">
            <BContainer>
                <a class="navbar-brand" href="#home">
                    <i class="ri-plant-line"></i>
                    <span class="brand-text">Bouyant Rice Trading</span>
                </a>
                <button class="navbar-toggler" type="button" @click="toggleMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" :class="{ 'show': menuOpen }">
                    <ul class="navbar-nav ms-auto">
                    </ul>
                </div>
            </BContainer>
        </nav>

        <!-- Hero Section -->
        <section id="home" class="hero-section">
            <BContainer>
                <BRow class="align-items-center min-vh-100">
                    <BCol lg="6" data-aos="fade-right" data-aos-duration="1000" data-aos-delay="200">
                        <div class="hero-content">
                            <h1 class="hero-title" data-aos="zoom-in" data-aos-duration="1200" data-aos-delay="400">
                                Premium Quality Rice
                                <span class="highlight" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="600">From Farm to Table</span>
                            </h1>
                            <p class="hero-description" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="800">
                                Experience the finest selection of premium rice varieties, carefully sourced and processed
                                to bring you the best quality grains for your family.
                            </p>
                            <div class="hero-buttons">
                                <BButton variant="primary" size="lg" class="me-3" @click.prevent="scrollToSection('contact')">
                                    <i class="ri-mail-line me-2"></i>
                                    Contact Us
                                </BButton>
                                <BButton variant="outline-light" size="lg" @click.prevent="scrollToSection('about')">
                                    Learn More
                                    <i class="ri-arrow-right-line ms-2"></i>
                                </BButton>
                            </div>

                        </div>
                    </BCol>
                    <BCol lg="6" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="300">
                        <div class="hero-image">
                            <div class="image-wrapper" data-aos="zoom-in" data-aos-duration="1200" data-aos-delay="500">
                                <i class="ri-plant-fill rice-icon" data-aos="bounce-in" data-aos-duration="1500" data-aos-delay="700"></i>
                            </div>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </section>

        <!-- About Section -->
        <section id="about" class="about-section py-5">
            <BContainer>
                <div class="section-header text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">About Our Business</h2>
                    <p class="section-subtitle">Committed to Quality and Excellence</p>
                </div>
                <BRow class="align-items-center">
                    <BCol lg="6" class="mb-4 mb-lg-0" data-aos="fade-right">
                        <div class="about-image-grid">
                            <div class="grid-item item-1">
                                <i class="ri-seedling-line"></i>
                            </div>
                            <div class="grid-item item-2">
                                <i class="ri-leaf-line"></i>
                            </div>
                            <div class="grid-item item-3">
                                <i class="ri-plant-line"></i>
                            </div>
                        </div>
                    </BCol>
                    <BCol lg="6" data-aos="fade-left">
                        <div class="about-content">
                            <h3 class="about-title">Your Trusted Rice Partner Since 2009</h3>
                            <p class="about-text">
                                We are a family-owned rice business dedicated to providing the highest quality rice 
                                to families across the nation. Our commitment to excellence starts from the paddy 
                                fields and continues through every step of processing and packaging.
                            </p>
                            <p class="about-text">
                                With over 15 years of experience in the rice industry, we have built strong 
                                relationships with local farmers and implemented sustainable farming practices 
                                that ensure both quality and environmental responsibility.
                            </p>
                            <div class="about-features mt-4">
                                <div class="feature-item">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    <span>100% Organic & Natural</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    <span>Direct from Farmers</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    <span>Quality Assured</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    <span>Sustainable Practices</span>
                                </div>
                            </div>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </section>

        <!-- Contact Us Section -->
        <section id="contact" class="contact-section py-5">
            <BContainer>
                <div class="section-header text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">Contact Us</h2>
                    <p class="section-subtitle">Get in touch with us for orders and inquiries</p>
                </div>
                <BRow class="align-items-center">
                    <BCol lg="6" class="mb-4 mb-lg-0" data-aos="fade-right">
                        <div class="contact-info">
                            <h3 class="contact-title">Get In Touch</h3>
                            <p class="contact-description">
                                Ready to experience premium quality rice? Contact us today for orders and inquiries.
                            </p>
                            <div class="contact-details mt-4">
                                <div class="contact-item">
                                    <i class="ri-map-pin-line"></i>
                                    <div>
                                        <h6>Address</h6>
                                        <p>123 Rice Valley Road, Agricultural District</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <i class="ri-phone-line"></i>
                                    <div>
                                        <h6>Phone</h6>
                                        <p>+1 (555) 123-4567</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <i class="ri-mail-line"></i>
                                    <div>
                                        <h6>Email</h6>
                                        <p>info@premiumrice.com</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <i class="ri-time-line"></i>
                                    <div>
                                        <h6>Business Hours</h6>
                                        <p>Mon - Sat: 8:00 AM - 6:00 PM</p>
                                    </div>
                                </div>
                            </div>
                            <div class="social-links mt-4">
                                <a href="javascript:void(0)" class="social-link" @click.prevent><i class="ri-facebook-fill"></i></a>
                                <a href="javascript:void(0)" class="social-link" @click.prevent><i class="ri-twitter-fill"></i></a>
                                <a href="javascript:void(0)" class="social-link" @click.prevent><i class="ri-instagram-line"></i></a>
                                <a href="javascript:void(0)" class="social-link" @click.prevent><i class="ri-linkedin-fill"></i></a>
                            </div>
                        </div>
                    </BCol>
                    <BCol lg="6" data-aos="fade-left">
                        <BCard class="contact-form-card">
                            <BCardBody>
                                <!-- Success/Error Message -->
                                <div v-if="submitStatus.show" :class="['alert', submitStatus.type === 'success' ? 'alert-success' : 'alert-danger']" role="alert">
                                    <i :class="submitStatus.type === 'success' ? 'ri-check-line' : 'ri-error-warning-line'"></i>
                                    {{ submitStatus.message }}
                                </div>
                                
                                <h4 class="form-title mb-4">Send Us a Message</h4>
                                <form @submit.prevent="submitContactForm">
                                    <div class="mb-3">
                                        <input 
                                            type="text" 
                                            class="form-control" 
                                            :class="{ 'is-invalid': errors.name }"
                                            placeholder="Your Name" 
                                            v-model="contactForm.name" 
                                            @input="clearError('name')"
                                        >
                                        <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <input 
                                            type="email" 
                                            class="form-control" 
                                            :class="{ 'is-invalid': errors.email }"
                                            placeholder="Your Email" 
                                            v-model="contactForm.email" 
                                            @input="clearError('email')"
                                        >
                                        <div v-if="errors.email" class="invalid-feedback">{{ errors.email }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <input 
                                            type="tel" 
                                            class="form-control" 
                                            :class="{ 'is-invalid': errors.phone }"
                                            placeholder="Your Phone" 
                                            v-model="contactForm.phone"
                                            @input="clearError('phone')"
                                        >
                                        <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <textarea 
                                            class="form-control" 
                                            :class="{ 'is-invalid': errors.message }"
                                            rows="4" 
                                            placeholder="Your Message" 
                                            v-model="contactForm.message"
                                            @input="clearError('message')"
                                        ></textarea>
                                        <div v-if="errors.message" class="invalid-feedback">{{ errors.message }}</div>
                                    </div>
                                    <BButton variant="primary" type="submit" class="w-100">
                                        <i class="ri-send-plane-line me-2"></i>
                                        Send Message
                                    </BButton>
                                </form>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </section>

        <!-- Footer -->
        <footer class="footer-section">
            <BContainer>
                <BRow class="align-items-center">
                    <BCol md="6" class="text-center text-md-start mb-3 mb-md-0">
                        <p class="footer-text mb-0">
                            &copy; {{ currentYear }} Bouyant Rice Trading. All rights reserved.
                        </p>
                    </BCol>
                    <BCol md="6" class="text-center text-md-end">
                        <div class="footer-links">
                            <a href="javascript:void(0)" class="footer-link" @click.prevent>Privacy Policy</a>
                            <span class="separator">|</span>
                            <a href="javascript:void(0)" class="footer-link" @click.prevent>Terms of Service</a>
                            <span class="separator">|</span>
                            <a href="javascript:void(0)" class="footer-link" @click.prevent>Contact</a>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </footer>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AOS from 'aos';
import 'aos/dist/aos.css';

// ============================================================================
// STATE MANAGEMENT
// ============================================================================
const isScrolled = ref(false);
const menuOpen = ref(false);
const hideNav = ref(false);

const contactForm = ref({
    name: '',
    email: '',
    phone: '',
    message: ''
});

const errors = ref({
    name: '',
    email: '',
    phone: '',
    message: ''
});

const submitStatus = ref({
    show: false,
    type: '',
    message: ''
});

// Get current year for footer
const currentYear = computed(() => new Date().getFullYear());

// ============================================================================
// EVENT HANDLERS
// ============================================================================
const handleScroll = () => {
    const scrollTop = window.scrollY;
    const heroHeight = window.innerHeight;
    
    isScrolled.value = scrollTop > 50;
    
    // Show nav at very top (0-50px) and past hero section, hide in between
    if (scrollTop <= 50) {
        hideNav.value = false;
    } else if (scrollTop < heroHeight - 100) {
        hideNav.value = true;
    } else {
        hideNav.value = false;
    }
};

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const scrollToSection = (sectionId) => {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const clearError = (field) => {
    errors.value[field] = '';
};

const validateForm = () => {
    let isValid = true;
    errors.value = { name: '', email: '', phone: '', message: '' };
    
    if (!contactForm.value.name.trim()) {
        errors.value.name = 'Name is required';
        isValid = false;
    }
    
    if (!contactForm.value.email.trim()) {
        errors.value.email = 'Email is required';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactForm.value.email)) {
        errors.value.email = 'Please enter a valid email address';
        isValid = false;
    }
    
    if (!contactForm.value.message.trim()) {
        errors.value.message = 'Message is required';
        isValid = false;
    }
    
    return isValid;
};

const submitContactForm = async () => {
    // Validate form first
    if (!validateForm()) {
        submitStatus.value = {
            show: true,
            type: 'error',
            message: 'Please fill in all required fields correctly.'
        };
        
        // Hide error message after 5 seconds
        setTimeout(() => {
            submitStatus.value.show = false;
        }, 5000);
        return;
    }
    
    try {
        const response = await axios.post('/api/contacts', {
            name: contactForm.value.name,
            email: contactForm.value.email,
            phone: contactForm.value.phone,
            message: contactForm.value.message
        });
        
        if (response.data.status) {
            submitStatus.value = {
                show: true,
                type: 'success',
                message: response.data.message || 'Thank you for your message! We will get back to you soon.'
            };
            contactForm.value = { name: '', email: '', phone: '', message: '' };
            
            // Hide success message after 5 seconds
            setTimeout(() => {
                submitStatus.value.show = false;
            }, 5000);
        }
    } catch (error) {
        console.error('Error submitting contact form:', error);
        
        // Handle validation errors from backend
        if (error.response && error.response.data.errors) {
            const backendErrors = error.response.data.errors;
            if (backendErrors.name) errors.value.name = backendErrors.name[0];
            if (backendErrors.email) errors.value.email = backendErrors.email[0];
            if (backendErrors.phone) errors.value.phone = backendErrors.phone[0];
            if (backendErrors.message) errors.value.message = backendErrors.message[0];
            
            submitStatus.value = {
                show: true,
                type: 'error',
                message: 'Please correct the errors below and try again.'
            };
        } else {
            submitStatus.value = {
                show: true,
                type: 'error',
                message: 'There was an error sending your message. Please try again.'
            };
        }
        
        // Hide error message after 5 seconds
        setTimeout(() => {
            submitStatus.value.show = false;
        }, 5000);
    }
};

// ============================================================================
// LIFECYCLE HOOKS
// ============================================================================
onMounted(() => {
    window.addEventListener('scroll', handleScroll);

    // Initialize AOS animations
    AOS.init({
        duration: 800,
        easing: 'ease-in-out',
        once: true,
        offset: 100
    });

    // Scroll to top on page load to prevent auto-scrolling to hash
    window.scrollTo(0, 0);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<script>
export default {
    layout: null,
}
</script>

<style scoped>
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    animation: slideDown 0.3s ease-out;
}

.alert-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}

.alert-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
}

.alert i {
    font-size: 1.25rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Form Validation Styles */
.is-invalid {
    border-color: #ef4444 !important;
}

.is-invalid:focus {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
}

.invalid-feedback {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 4px;
    display: block;
}

/* Existing styles remain the same */
.brt-landing-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0f9ff 100%);
    position: relative;
    overflow-x: hidden;
}

.background-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.floating-grain {
    position: absolute;
    width: 20px;
    height: 20px;
    background: rgba(194, 178, 128, 0.15);
    border-radius: 50%;
    animation: float 15s infinite ease-in-out;
}

.floating-grain:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
.floating-grain:nth-child(2) { left: 30%; top: 60%; animation-delay: 2s; }
.floating-grain:nth-child(3) { left: 50%; top: 30%; animation-delay: 4s; }
.floating-grain:nth-child(4) { left: 70%; top: 70%; animation-delay: 6s; }
.floating-grain:nth-child(5) { left: 85%; top: 40%; animation-delay: 8s; }
.floating-grain:nth-child(6) { left: 20%; top: 80%; animation-delay: 10s; }

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.15; }
    50% { transform: translateY(-100px) rotate(180deg); opacity: 0.3; }
}

.navbar {
    padding: 20px 0;
    transition: all 0.3s ease;
    background: transparent;
}

.navbar.scrolled {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    padding: 12px 0;
}

.navbar.hidden {
    transform: translateY(-100%);
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 1.5rem;
    color: #1e293b !important;
}

.navbar-brand i {
    font-size: 2rem;
    color: #059669;
}

.brand-text {
    background: linear-gradient(135deg, #059669, #10b981);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-section {
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 120px 0 80px;
    position: relative;
    z-index: 1;
}

.hero-content {
    padding-right: 40px;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.2;
    color: #1e293b;
    margin-bottom: 24px;
}

.hero-title .highlight {
    display: block;
    background: linear-gradient(135deg, #059669, #10b981);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2rem;
    margin-top: 10px;
}

.hero-description {
    font-size: 1.125rem;
    color: #64748b;
    line-height: 1.8;
    margin-bottom: 32px;
}

.hero-buttons {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.hero-image {
    display: flex;
    justify-content: center;
    align-items: center;
}

.image-wrapper {
    width: 350px;
    height: 350px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 20px 60px rgba(5, 150, 105, 0.3);
}

.rice-icon {
    font-size: 150px;
    color: white;
}

.about-section {
    background: white;
    position: relative;
    z-index: 1;
}

.section-header {
    margin-bottom: 60px;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.section-subtitle {
    font-size: 1.125rem;
    color: #64748b;
}

.about-image-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    max-width: 300px;
    margin: 0 auto;
}

.grid-item {
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 20px;
    height: 150px;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 10px 30px rgba(5, 150, 105, 0.2);
}

.grid-item i {
    font-size: 60px;
    color: white;
}

.grid-item.item-1 { transform: translateY(0); }
.grid-item.item-2 { transform: translateY(30px); }
.grid-item.item-3 { grid-column: span 2; transform: translateY(-20px); }

.about-content {
    padding-left: 20px;
}

.about-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 20px;
}

.about-text {
    color: #64748b;
    line-height: 1.8;
    margin-bottom: 16px;
}

.about-features {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #1e293b;
    font-weight: 500;
}

.feature-item i {
    color: #10b981;
    font-size: 1.25rem;
}

.contact-section {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    position: relative;
    z-index: 1;
}

.contact-info {
    padding-right: 40px;
}

.contact-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.contact-description {
    color: #64748b;
    line-height: 1.8;
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.contact-item > i {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.contact-item h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}

.contact-item p {
    color: #64748b;
    margin-bottom: 0;
}

.social-links {
    display: flex;
    gap: 12px;
}

.social-link {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #64748b;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.social-link:hover {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    transform: translateY(-3px);
}

.contact-form-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    background: white;
}

.contact-form-card .card-body {
    padding: 40px;
}

.form-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.form-control {
    padding: 14px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    outline: none;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    padding: 14px 28px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(5, 150, 105, 0.3);
}

.footer-section {
    background: #1e293b;
    padding: 30px 0;
    position: relative;
    z-index: 1;
}

.footer-text {
    color: #94a3b8;
    font-size: 0.875rem;
}

.footer-links {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
}

.footer-link {
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.3s ease;
}

.footer-link:hover {
    color: white;
}

.separator {
    color: #475569;
}

@media (max-width: 991px) {
    .hero-content {
        padding-right: 0;
        text-align: center;
        margin-bottom: 40px;
    }

    .hero-title {
        font-size: 2.5rem;
    }

    .hero-title .highlight {
        font-size: 1.5rem;
    }

    .hero-buttons {
        justify-content: center;
    }

    .hero-image {
        margin-top: 20px;
    }

    .image-wrapper {
        width: 280px;
        height: 280px;
    }

    .rice-icon {
        font-size: 100px;
    }

    .about-content {
        padding-left: 0;
        text-align: center;
        margin-top: 40px;
    }

    .about-features {
        justify-content: center;
    }

    .contact-info {
        padding-right: 0;
        text-align: center;
        margin-bottom: 40px;
    }

    .contact-details {
        align-items: center;
    }

    .contact-item {
        justify-content: center;
    }

    .social-links {
        justify-content: center;
    }
}

@media (max-width: 767px) {
    .hero-section {
        padding: 100px 0 60px;
    }

    .hero-title {
        font-size: 2rem;
    }

    .section-title {
        font-size: 2rem;
    }

    .about-image-grid {
        max-width: 250px;
    }

    .grid-item {
        height: 120px;
    }

    .grid-item i {
        font-size: 45px;
    }

    .contact-form-card .card-body {
        padding: 24px;
    }

    .footer-links {
        flex-direction: column;
        gap: 12px;
    }

    .separator {
        display: none;
    }
}
</style>
