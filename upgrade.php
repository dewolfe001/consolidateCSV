<?php
// Simple Stripe checkout session creation
$publicKey = getenv('STRIPE_PUBLIC_KEY');
$secretKey = getenv('STRIPE_SECRET_KEY');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$secretKey) {
        die('Stripe not configured');
    }
    $data = http_build_query([
        'success_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/index.php?premium=1',
        'cancel_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/index.php',
        'payment_method_types[]' => 'card',
        'mode' => 'subscription',
        'line_items[0][price]' => getenv('STRIPE_PRICE_ID'),
        'client_reference_id' => session_id(),
    ]);
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_USERPWD, $secretKey.':');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $info = json_decode($resp, true);
    curl_close($ch);
    if (isset($info['url'])) {
        header('Location: '.$info['url']);
        exit;
    }
    echo 'Error creating Stripe session';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Upgrade</title>
</head>
<body>
<h1>Upgrade to Premium</h1>
<form method="post">
    <button type="submit">Proceed to Payment</button>
</form>
</body>
</html>
