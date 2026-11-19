<?php
require 'includes/bootstrap.php';
if (admin()) redirect('index.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([trim($_POST['email'] ?? '')]); $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['admin'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']]; redirect('index.php');
    }
    $error = 'Email or password is incorrect.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · HALA SPAIN</title><link rel="stylesheet" href="assets/app.css"></head><body class="login-page"><section class="login-card"><div class="login-art"><div class="brand light"><span>H</span><div>HALA SPAIN<small>Travel made effortless</small></div></div><div><p class="eyebrow">WELCOME BACK</p><h1>Everything your travelers see, managed in one place.</h1><p>Cars with drivers, stays, destinations and services—ready for the mobile experience.</p></div></div><form method="post"><p class="eyebrow">SECURE ACCESS</p><h2>Sign in to your dashboard</h2><p class="muted">Use your administrator account to continue.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><input type="hidden" name="_csrf" value="<?= csrf() ?>"><label>Email address<input type="email" name="email" value="admin@askustours.com" required></label><label>Password<input type="password" name="password" required></label><button class="btn primary wide">Sign in <span>→</span></button><small class="hint">Initial login: admin@askustours.com / password</small></form></section></body></html>
