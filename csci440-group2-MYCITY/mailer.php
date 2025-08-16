<?php
require 'vendor/autoload.php';
use Mailgun\Mailgun;

function sendEmail($to, $subject, $body) {
    // Your Mailgun credentials
    $mgDomain = "[redacted]"; // Your Mailgun domain (e.g., mg.yourdomain.com)
    $mgApiKey = "[redacted]"; // Your Mailgun API key
    
    try {
        $mg = Mailgun::create($mgApiKey);
        
        $result = $mg->messages()->send($mgDomain, [
            'from'    => 'Gridfix <noreply@your-domain.com>',
            'to'      => $to,
            'subject' => $subject,
            'text'    => strip_tags($body),
            'html'    => $body
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Mailgun Error: " . $e->getMessage());
        return false;
    }
}
?>