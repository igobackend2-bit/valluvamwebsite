<?php
// FIX: this used to be a blank page that silently destroyed the session.
// checkout.php and order_tracking.php redirect logged-out customers here,
// so they ended up on an empty white page. Now it sends them to the
// homepage and opens the login popup (see assets/js/login/login.js).
header('Location: /?login=1');
exit;
