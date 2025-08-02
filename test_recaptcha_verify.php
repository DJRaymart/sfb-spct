<?php
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    if (empty($recaptcha_response)) {
        echo "Please complete the reCAPTCHA.";
        exit;
    }
    
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response_keys = json_decode($result, true);
    
    echo "<h1>reCAPTCHA Verification Result</h1>";
    echo "<pre>";
    print_r($response_keys);
    echo "</pre>";
    
    if ($response_keys['success']) {
        echo "<p style='color: green;'>reCAPTCHA verification successful!</p>";
    } else {
        echo "<p style='color: red;'>reCAPTCHA verification failed.</p>";
        if (isset($response_keys['error-codes'])) {
            echo "<p>Error codes: " . implode(", ", $response_keys['error-codes']) . "</p>";
        }
    }
} else {
    echo "Invalid request method.";
}
?> 