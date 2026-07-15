<?php
require_once __DIR__ . '/../config/config.php';

function buildVerificationUrl($verificationToken)
{
    return rtrim(APP_BASE_URL, '/') . '/auth/verify_email.php?token=' . urlencode($verificationToken);
}

function sendVerificationEmail($buyerEmail, $buyerName, $verificationToken)
{
    $verificationUrl = buildVerificationUrl($verificationToken);
    $safeBuyerName = htmlspecialchars($buyerName, ENT_QUOTES, 'UTF-8');
    $safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    $subject = 'Verify your TELA account';
    $htmlMessage = '
        <p>Hello ' . $safeBuyerName . ',</p>
        <p>Thank you for registering with TELA. Please verify your email address to activate your buyer account.</p>
        <p><a href="' . $safeVerificationUrl . '">Verify my email address</a></p>
        <p>If the button does not work, copy and paste this link into your browser:</p>
        <p>' . $safeVerificationUrl . '</p>
    ';

    $plainTextMessage = "Hello " . $buyerName . ",\n\n"
        . "Thank you for registering with TELA. Please verify your email address to activate your buyer account.\n\n"
        . "Verification link: " . $verificationUrl . "\n\n"
        . "If you did not register for TELA, you can ignore this email.";

    return sendBrevoEmail($buyerEmail, $buyerName, $subject, $htmlMessage, $plainTextMessage);
}

function sendBrevoEmail($toEmail, $toName, $subject, $htmlContent, $textContent)
{
    if (BREVO_API_KEY === '' || BREVO_SENDER_EMAIL === '') {
        error_log('TELA Brevo email: configuration missing.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('TELA Brevo email: cURL extension unavailable.');
        return false;
    }

    $payload = [
        'sender' => [
            'name' => BREVO_SENDER_NAME,
            'email' => BREVO_SENDER_EMAIL
        ],
        'to' => [
            [
                'email' => $toEmail,
                'name' => $toName
            ]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
        'textContent' => $textContent
    ];

    $jsonPayload = json_encode($payload);

    if ($jsonPayload === false) {
        error_log('TELA Brevo email: JSON encoding failed.');
        return false;
    }

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');

    if ($curl === false) {
        error_log('TELA Brevo email: cURL initialization failed.');
        return false;
    }

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'api-key: ' . BREVO_API_KEY,
        'accept: application/json',
        'content-type: application/json'
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonPayload);

    $response = curl_exec($curl);

    if ($response === false) {
        $curlErrorNumber = curl_errno($curl);
        error_log('TELA Brevo email: cURL transport error category ' . $curlErrorNumber . '.');
        curl_close($curl);
        return false;
    }

    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    error_log('TELA Brevo email: HTTP status ' . $httpStatus . '.');

    if ($httpStatus < 200 || $httpStatus >= 300) {
        error_log('TELA Brevo email: non-success HTTP status.');
        return false;
    }

    if ($response === '') {
        error_log('TELA Brevo email: empty API response.');
        return false;
    }

    $responseData = json_decode($response, true);

    if (!is_array($responseData) || json_last_error() !== JSON_ERROR_NONE) {
        error_log('TELA Brevo email: malformed JSON response.');
        return false;
    }

    if (!isset($responseData['messageId']) && !isset($responseData['messageIds'])) {
        error_log('TELA Brevo email: success response missing message ID.');
        return false;
    }

    error_log('TELA Brevo email: accepted by Brevo.');
    return true;
}
