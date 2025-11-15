<template>
    <Head title="Installation"></Head>
    <div class="d-flex flex-column min-vh-100 justify-content-center align-items-center">
        <div class="text-center mb-2">
            <a to="/" class="d-block auth-logo">
                <img src="/images/logo-sm.png" alt="" height="50" class="auth-logo-dark mx-auto" />
            </a>
            <p class="font-size-11 mt-3"> <br> BRT Accountitng Software</p>
          
        </div>
        
        <div class="mb-5 customform" style="width: 900px;">
            <div class="text-center mb-2">
                <p class="text-muted">Thank you for registering your account. We will update you once the system is accessible.</p>
                <p class="text-muted mt-n3">Click here to <Link href="/logout" method="post" class="fw-semibold text-danger text-decoration-underline">Logout</Link></p>
            </div>
        </div>
        
        <div class="text-center">
            <div>
                <p>© BRT Accounting Software <i class='bx bxs-heart text-danger'></i> 2025</p>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    layout: null,
}
</script>
<style>
.row {
  display: flex;
}

.item {
  margin: 5px;
  padding: 10px;
  border: 1px solid #ccc;
}
</style>