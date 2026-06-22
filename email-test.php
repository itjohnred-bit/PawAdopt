<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$to = $_GET['to'] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Usage: /email-test.php?to=you@gmail.com\n";
    exit;
}

$ok  = mailer_send($to, 'PawAdopt SMTP test', sprintf(
    "Hi!\n\nIf you're reading this email, SMTP is working from Render.\n%s\n",
    date('c')
));

$logPath = '/opt/render/project/storage/logs/mailer_test.log';
echo $ok ? "OK - check the inbox of {$to}\n" : "FAIL\n";
echo "Detail log: {$logPath}\n";
