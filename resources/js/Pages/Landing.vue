<template>
    <Head title="Premium Rice Business" />
    <div class="landing-page">
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
                        <li class="nav-item">
                            <BButton variant="primary" class="cta-btn" @click="showModal = true">Check my Account</BButton>
                        </li>
                    </ul>
                </div>
            </BContainer>
        </nav>

        <!-- Account Check Modal -->
        <BModal v-model="showModal" title="Check Your Account" centered size="lg" hide-footer>
            <div class="modal-content-wrapper">
                <!-- Email Input Form -->
                <div v-if="!accountChecked">
                    <p class="modal-description">Enter your email address to check your account status and access your rice orders.</p>
                    <form @submit.prevent="checkAccount">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email"
                                v-model="accountEmail" 
                                placeholder="Enter your email" 
                                required
                            />
                        </div>
                        <div class="d-grid gap-2">
                            <BButton variant="primary" type="submit" size="lg">
                                <i class="ri-search-line me-2"></i>
                                Check Account
                            </BButton>
                            <BButton variant="outline-secondary" @click="showModal = false">
                                Cancel
                            </BButton>
                        </div>
                    </form>
                </div>

                <!-- Account Information Display -->
                <div v-else class="account-info-display">
                    <div class="account-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Welcome back!</h5>
                                <p class="text-muted mb-0">{{ accountEmail }}</p>
                            </div>
                            <BButton variant="outline-secondary" size="sm" @click="resetModal">
                                <i class="ri-arrow-left-line me-1"></i>
                                Back
                            </BButton>
                        </div>
                    </div>

                    <!-- Balance Card -->
                    <BCard class="balance-card mb-4">
                        <BCardBody>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1">Account Balance</p>
                                    <h3 class="balance-amount mb-0">₱{{ accountData.balance.toLocaleString() }}</h3>
                                </div>
                                <div class="balance-icon">
                                    <i class="ri-wallet-3-line"></i>
                                </div>
                            </div>
                        </BCardBody>
                    </BCard>

                    <!-- Order Status Section -->
                    <div class="orders-section">
                        <h6 class="section-title mb-3">
                            <i class="ri-shopping-bag-line me-2"></i>
                            Recent Orders
                        </h6>
                        
                        <div v-if="accountData.orders.length > 0">
                            <BCard v-for="(order, index) in accountData.orders" :key="index" class="order-card mb-3">
                                <BCardBody>
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="order-id mb-1">Order #{{ order.id }}</h6>
                                            <p class="order-date text-muted mb-0">{{ order.date }}</p>
                                        </div>
                                        <span :class="['status-badge', `status-${order.status.toLowerCase()}`]">
                                            {{ order.status }}
                                        </span>
                                    </div>
                                    
                                    <div class="order-items mb-3">
                                        <div v-for="(item, idx) in order.items" :key="idx" class="order-item">
                                            <i class="ri-checkbox-circle-line text-success me-2"></i>
                                            <span>{{ item.name }} - {{ item.quantity }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="order-total">
                                            <strong>Total: ₱{{ order.total.toLocaleString() }}</strong>
                                        </div>
                                        <BButton variant="outline-primary" size="sm">
                                            <i class="ri-eye-line me-1"></i>
                                            View Details
                                        </BButton>
                                    </div>
                                </BCardBody>
                            </BCard>
                        </div>
                        
                        <div v-else class="text-center py-4">
                            <i class="ri-inbox-line" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No orders found</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 mt-4">
                        <BButton variant="outline-secondary" @click="showModal = false">
                            Close
                        </BButton>
                    </div>
                </div>
            </div>
        </BModal>

        <!-- Hero Section -->
        <section id="home" class="hero-section">
            <BContainer>
                <BRow class="align-items-center min-vh-100">
                    <BCol lg="6" data-aos="fade-right">
                        <div class="hero-content">
                            <h1 class="hero-title">
                                Premium Quality Rice
                                <span class="highlight">From Farm to Table</span>
                            </h1>
                            <p class="hero-description">
                                Experience the finest selection of premium rice varieties, carefully sourced and processed 
                                to bring you the best quality grains for your family.
                            </p>
                            <div class="hero-buttons">
                                <BButton variant="primary" size="lg" class="me-3" href="#contact">
                                    <i class="ri-mail-line me-2"></i>
                                    Contact Us
                                </BButton>
                                <BButton variant="outline-light" size="lg" href="#about">
                                    Learn More
                                    <i class="ri-arrow-right-line ms-2"></i>
                                </BButton>
                            </div>
                            <div class="hero-stats mt-5">
                                <div class="stat-item">
                                    <h3>15+</h3>
                                    <p>Years Experience</p>
                                </div>
                                <div class="stat-item">
                                    <h3>50K+</h3>
                                    <p>Happy Customers</p>
                                </div>
                                <div class="stat-item">
                                    <h3>100%</h3>
                                    <p>Organic</p>
                                </div>
                            </div>
                        </div>
                    </BCol>
                    <BCol lg="6" data-aos="fade-left">
                        <div class="hero-image">
                            <div class="image-wrapper">
                                <i class="ri-plant-fill rice-icon"></i>
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
                                <a href="#" class="social-link"><i class="ri-facebook-fill"></i></a>
                                <a href="#" class="social-link"><i class="ri-twitter-fill"></i></a>
                                <a href="#" class="social-link"><i class="ri-instagram-line"></i></a>
                                <a href="#" class="social-link"><i class="ri-linkedin-fill"></i></a>
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
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isScrolled = ref(false);
const menuOpen = ref(false);
const showModal = ref(false);
const accountEmail = ref('');
const accountChecked = ref(false);
const hideNav = ref(false);
let lastScrollTop = 0;

const contactForm = ref({
    name: '',
    email: '',
    phone: '',
    message: ''
});

// Sample account data (in real app, this would come from API)
const accountData = ref({
    balance: 15750,
    orders: [
        {
            id: '2024001',
            date: 'January 15, 2024',
            status: 'Delivered',
            items: [
                { name: 'Premium Basmati Rice', quantity: '10kg' },
                { name: 'Jasmine Rice', quantity: '5kg' }
            ],
            total: 3500
        },
        {
            id: '2024002',
            date: 'January 20, 2024',
            status: 'Processing',
            items: [
                { name: 'Organic Brown Rice', quantity: '15kg' }
            ],
            total: 4200
        },
        {
            id: '2024003',
            date: 'January 25, 2024',
            status: 'Pending',
            items: [
                { name: 'Wild Rice Blend', quantity: '8kg' },
                { name: 'Sushi Rice', quantity: '5kg' }
            ],
            total: 5100
        }
    ]
});

const handleScroll = () => {
    const scrollTop = window.scrollY;
    const heroHeight = window.innerHeight;
    
    // Check if scrolled past 50px
    isScrolled.value = scrollTop > 50;
    
    // Show nav at very top (0-50px) and past hero section, hide in between
    if (scrollTop <= 50) {
        // At the very top - show nav
        hideNav.value = false;
    } else if (scrollTop < heroHeight - 100) {
        // In hero section but not at top - hide nav
        hideNav.value = true;
    } else {
        // Past hero section - show nav
        hideNav.value = false;
    }
    
    lastScrollTop = scrollTop;
};

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};

const checkAccount = () => {
    // In real app, this would make an API call to check the account
    accountChecked.value = true;
};

const resetModal = () => {
    accountChecked.value = false;
    accountEmail.value = '';
};

const submitContactForm = () => {
    alert('Thank you for your message! We will get back to you soon.');
    contactForm.value = { name: '', email: '', phone: '', message: '' };
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
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
/* Global Styles */
.landing-page {
    position: relative;
    overflow-x: hidden;
}

html {
    scroll-behavior: smooth;
    scroll-padding-top: 100px;
}

/* Background Animation */
.background-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
    pointer-events: none;
}

.floating-grain {
    position: absolute;
    background: rgba(46, 139, 87, 0.08);
    border-radius: 50%;
    animation: float 20s infinite ease-in-out;
}

.floating-grain:nth-child(1) {
    width: 100px;
    height: 100px;
    top: 10%;
    left: 5%;
    animation-delay: 0s;
}

.floating-grain:nth-child(2) {
    width: 80px;
    height: 80px;
    top: 60%;
    left: 10%;
    animation-delay: 2s;
}

.floating-grain:nth-child(3) {
    width: 120px;
    height: 120px;
    top: 30%;
    left: 80%;
    animation-delay: 4s;
}

.floating-grain:nth-child(4) {
    width: 60px;
    height: 60px;
    top: 70%;
    left: 75%;
    animation-delay: 1s;
}

.floating-grain:nth-child(5) {
    width: 90px;
    height: 90px;
    top: 20%;
    left: 50%;
    animation-delay: 3s;
}

.floating-grain:nth-child(6) {
    width: 70px;
    height: 70px;
    top: 80%;
    left: 40%;
    animation-delay: 5s;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.6;
    }
    50% {
        transform: translateY(-30px) rotate(180deg);
        opacity: 0.9;
    }
}

/* Navigation */
.navbar {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    backdrop-filter: blur(10px);
    transition: all 0.5s ease;
    padding: 1.5rem 0;
    z-index: 1000;
}

.navbar.scrolled {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
    padding: 0.8rem 0;
}

.navbar.hidden {
    transform: translateY(-100%);
}

.navbar-brand {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.navbar-brand i {
    font-size: 2rem;
    color: white;
}

.brand-text {
    color: white;
}

.nav-link {
    color: white;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: color 0.3s;
    text-decoration: none;
}

.nav-link:hover {
    color: rgba(255, 255, 255, 0.8);
}

.cta-btn {
    background: white;
    color: #2e8b57;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s;
}

.cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.95);
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #f5f7fa 0%, #e8f5e9 100%);
    position: relative;
    z-index: 1;
    padding-top: 100px;
}

.hero-content {
    padding: 2rem 0;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    color: #2c3e50;
    line-height: 1.2;
    margin-bottom: 1.5rem;
}

.hero-title .highlight {
    display: block;
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-description {
    font-size: 1.2rem;
    color: #7f8c8d;
    margin-bottom: 2rem;
    line-height: 1.8;
}

.hero-buttons .btn {
    border-radius: 30px;
    padding: 0.8rem 2rem;
    font-weight: 600;
}

.btn-outline-light {
    color: #2e8b57;
    border-color: #2e8b57;
}

.btn-outline-light:hover {
    background: #2e8b57;
    color: white;
}

.hero-stats {
    display: flex;
    gap: 3rem;
}

.stat-item h3 {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2e8b57;
    margin-bottom: 0.5rem;
}

.stat-item p {
    color: #7f8c8d;
    font-weight: 500;
}

.hero-image {
    display: flex;
    justify-content: center;
    align-items: center;
}

.image-wrapper {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 20px 60px rgba(46, 139, 87, 0.3);
    animation: pulse 3s infinite;
}

.rice-icon {
    font-size: 12rem;
    color: white;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Section Styles */
.section-header {
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #7f8c8d;
}

/* About Section */
.about-section {
    background: white;
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.about-image-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.grid-item {
    aspect-ratio: 1;
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    border-radius: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s;
}

.grid-item:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 30px rgba(46, 139, 87, 0.2);
}

.grid-item i {
    font-size: 4rem;
    color: #2e8b57;
}

.grid-item.item-3 {
    grid-column: span 2;
}

.about-title {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1.5rem;
}

.about-text {
    color: #7f8c8d;
    line-height: 1.8;
    margin-bottom: 1rem;
}

.about-features .feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.about-features .feature-item i {
    color: #2e8b57;
    font-size: 1.5rem;
}

.about-features .feature-item span {
    color: #2c3e50;
    font-weight: 500;
}

/* Modal Styles */
.modal-content-wrapper {
    padding: 1rem;
}

.modal-description {
    color: #7f8c8d;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.modal-content-wrapper .form-label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.modal-content-wrapper .form-control {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 0.8rem 1rem;
    font-size: 1rem;
}

.modal-content-wrapper .form-control:focus {
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.modal-content-wrapper .btn-primary {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    padding: 0.8rem;
}

.modal-content-wrapper .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
}

.modal-content-wrapper .btn-outline-secondary {
    border-radius: 10px;
    font-weight: 600;
    padding: 0.8rem;
}

/* Contact Section */
.contact-section {
    background: linear-gradient(135deg, #f5f7fa 0%, #e8f5e9 100%);
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.contact-title {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.contact-description {
    color: #7f8c8d;
    font-size: 1.1rem;
    line-height: 1.8;
}

.contact-details {
    margin-top: 2rem;
}

.contact-item {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.contact-item i {
    font-size: 2rem;
    color: #2e8b57;
    flex-shrink: 0;
}

.contact-item h6 {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.contact-item p {
    color: #7f8c8d;
    margin: 0;
}

.social-links {
    display: flex;
    gap: 1rem;
}

.social-link {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    font-size: 1.3rem;
    transition: all 0.3s;
    text-decoration: none;
}

.social-link:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
}

.contact-form-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.form-title {
    font-weight: 700;
    color: #2c3e50;
}

.contact-form-card .form-control {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 0.8rem 1rem;
}

.contact-form-card .form-control:focus {
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.contact-form-card .btn-primary {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border: none;
    border-radius: 10px;
    font-weight: 600;
}

.contact-form-card .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
}

/* Responsive Design */
@media (max-width: 991px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-stats {
        gap: 2rem;
    }
    
    .hero-image .image-wrapper {
        width: 300px;
        height: 300px;
    }
    
    .rice-icon {
        font-size: 8rem;
    }
}

@media (max-width: 767px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .about-image-grid {
        grid-template-columns: 1fr;
    }
    
    .grid-item.item-3 {
        grid-column: span 1;
    }
    
    .navbar-collapse {
        background: white;
        padding: 1rem;
        border-radius: 10px;
        margin-top: 1rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .nav-item {
        margin: 0.5rem 0;
    }
}

@media (max-width: 576px) {
    .hero-buttons {
        flex-direction: column;
        gap: 1rem;
    }
    
    .hero-buttons .btn {
        width: 100%;
    }
    
    .contact-item {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
/* Account Info Display Styles */
.account-info-display { max-height: 70vh; overflow-y: auto; }
.account-header h5 { font-weight: 700; color: #2c3e50; }
.balance-card { background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%); border: none; border-radius: 15px; color: white; }
.balance-amount { font-size: 2rem; font-weight: 800; color: white; }
.balance-icon { width: 60px; height: 60px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; }
.balance-icon i { font-size: 2rem; color: white; }
.orders-section .section-title { font-size: 1.1rem; font-weight: 700; color: #2c3e50; }
.order-card { border: 1px solid #e9ecef; border-radius: 12px; transition: all 0.3s; }
.order-card:hover { box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
.order-id { font-weight: 700; color: #2c3e50; }
.order-date { font-size: 0.9rem; }
.status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.status-delivered { background: #d4edda; color: #155724; }
.status-processing { background: #fff3cd; color: #856404; }
.status-pending { background: #f8d7da; color: #721c24; }
.order-items { border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; padding: 0.8rem 0; }
.order-item { display: flex; align-items: center; margin-bottom: 0.5rem; font-size: 0.95rem; color: #6c757d; }
.order-item:last-child { margin-bottom: 0; }
.order-total { color: #2c3e50; }
</style>
