<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>reCAPTCHA Test</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <h1>reCAPTCHA Test</h1>
    <p>This page tests if the reCAPTCHA is working correctly.</p>
    
    <form action="test_recaptcha_verify.php" method="POST">
        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
        <br/>
        <input type="submit" value="Submit">
    </form>
</body>
</html> 