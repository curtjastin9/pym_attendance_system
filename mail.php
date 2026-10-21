<?php
/**
 * Minimal SMTP sender (no libraries). Include-only file.
 * Env overrides: SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_FROM_NAME
 */
function pym_smtp_cfg() {
    return [
        'host' => pym_env('SMTP_HOST') ?: 'smtp.gmass.co',
        'user' => pym_env('SMTP_USER') ?: 'gmass',
        'pass' => pym_env('SMTP_PASS') ?: 'b96473bc-61b8-43ee-816f-d2b82c327b27',
        'from' => pym_env('SMTP_FROM') ?: 'curtjastin9@gmail.com',
        'name' => pym_env('SMTP_FROM_NAME') ?: 'MQAP - PYM Attendance',
    ];
}

function smtp_read($fp) {
    $r = '';
    while (($l = fgets($fp, 515)) !== false) {
        $r .= $l;
        if (strlen($l) < 4 || $l[3] === ' ') break;
    }
    return $r;
}
function smtp_cmd($fp, $cmd, $ok) {
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $r = smtp_read($fp);
    if (!in_array((int)substr($r, 0, 3), (array)$ok, true)) throw new RuntimeException(trim($r) ?: 'No response from SMTP server');
    return $r;
}

function smtp_deliver($c, $port, $mode, $to, $data) {
    $verify = is_readable(__DIR__ . '/cacert.pem');
    $ssl = ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify];
    if ($verify) $ssl['cafile'] = __DIR__ . '/cacert.pem';
    $ctx = stream_context_create(['ssl' => $ssl]);
    $fp = @stream_socket_client(($mode === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port, $en, $es, 6, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("connect failed ($es)");
    stream_set_timeout($fp, 12);
    try {
        smtp_cmd($fp, null, 220);
        smtp_cmd($fp, 'EHLO pym.local', 250);
        if ($mode === 'tls') {
            smtp_cmd($fp, 'STARTTLS', 220);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS handshake failed');
            smtp_cmd($fp, 'EHLO pym.local', 250);
        }
        smtp_cmd($fp, 'AUTH LOGIN', 334);
        smtp_cmd($fp, base64_encode($c['user']), 334);
        smtp_cmd($fp, base64_encode($c['pass']), 235);
        smtp_cmd($fp, 'MAIL FROM:<' . $c['from'] . '>', 250);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', 354);
        fwrite($fp, $data . "\r\n.\r\n");
        smtp_cmd($fp, null, 250);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        @fclose($fp);
    }
}

/** Returns [bool ok, string error]. */
function pym_send_mail($to, $subject, $html, $text) {
    @set_time_limit(40);
    $c = pym_smtp_cfg();
    $b = 'b' . bin2hex(random_bytes(8));
    $enc = fn($s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $head = "Date: " . date('r') . "\r\n"
        . "From: =?UTF-8?B?" . base64_encode($c['name']) . "?= <{$c['from']}>\r\n"
        . "To: <$to>\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "Message-ID: <" . bin2hex(random_bytes(8)) . "@pym.local>\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n";
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($text) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html) . "\r\n"
        . "--$b--";
    $data = $head . $body;
    $errs = [];
    foreach ([[465, 'ssl'], [587, 'tls'], [2525, 'tls']] as [$port, $mode]) {
        try { smtp_deliver($c, $port, $mode, $to, $data); return [true, '']; }
        catch (Throwable $e) { $errs[] = "$port: " . $e->getMessage(); error_log('SMTP ' . $port . ' ' . $e->getMessage()); }
    }
    return [false, implode(' | ', $errs)];
}

function pym_code_mail($to, $code, $title, $intro) {
    $html = '<div style="font-family:Arial,sans-serif;max-width:460px;margin:auto;padding:24px;border:1px solid #e2e8f0;border-radius:14px">'
        . '<h2 style="margin:0 0 8px;color:#0F1F5C">MQAP – PYM Attendance</h2>'
        . '<p style="color:#334155;font-size:14px">' . htmlspecialchars($intro) . '</p>'
        . '<div style="font-size:34px;font-weight:800;letter-spacing:8px;text-align:center;background:#EFF6FF;color:#1D4ED8;border-radius:12px;padding:16px;margin:18px 0">' . $code . '</div>'
        . '<p style="color:#64748b;font-size:12px">This code expires in 10 minutes. If you did not request it, ignore this email.</p></div>';
    $text = "$intro\n\nCode: $code\n\nThis code expires in 10 minutes. If you did not request it, ignore this email.";
    return pym_send_mail($to, $title, $html, $text);
}
