<?php

/**
 * '/' is registered twice in routes/web.php: once for guests (WelcomeController)
 * and again, later, as the authenticated dashboard. The later registration wins,
 * so the root URL is the dashboard and a guest is bounced to the login screen.
 */
it('redirects a guest from the root URL to the login screen', function () {
    $this->get('/')->assertRedirect('/login');
});
