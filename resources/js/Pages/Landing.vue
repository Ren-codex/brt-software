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
                                <h4 class="form-title mb-4">Send Us a Message</h4>
                                <form @submit.prevent="submitContactForm">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" placeholder="Your Name" v-model="contactForm.name" required>
                                    </div>
                                    <div class="mb-3">
                                        <input type="email" class="form-control" placeholder="Your Email" v-model="contactForm.email" required>
                                    </div>
                                    <div class="mb-3">
                                        <input type="tel" class="form-control" placeholder="Your Phone" v-model="contactForm.phone">
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" rows="4" placeholder="Your Message" v-model="contactForm.message" required></textarea>
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

const submitContactForm = () => {
    // TODO: Implement API call to submit contact form
    alert('Thank you for your message! We will get back to you soon.');
    contactForm.value = { name: '', email: '', phone: '', message: '' };
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
