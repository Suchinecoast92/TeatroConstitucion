<?php
/**
 * Rate limiting simple para login (por IP, archivo temporal).
 */

if (defined('TEATRO_LOGIN_RL_INCLUDED')) {
    return;
}
define('TEATRO_LOGIN_RL_INCLUDED', true);

function teatro_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
}

function teatro_login_rl_path(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'teatro_login_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $ip = teatro_client_ip();
    return $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
}

/**
 * @return array{attempts:int,first:int,blocked_until:int}
 */
function teatro_login_rl_read(): array
{
    $path = teatro_login_rl_path();
    $defaults = ['attempts' => 0, 'first' => time(), 'blocked_until' => 0];
    if (!is_file($path)) {
        return $defaults;
    }
    $raw = @file_get_contents($path);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return $defaults;
    }
    return [
        'attempts' => (int) ($data['attempts'] ?? 0),
        'first' => (int) ($data['first'] ?? time()),
        'blocked_until' => (int) ($data['blocked_until'] ?? 0),
    ];
}

function teatro_login_rl_write(array $data): void
{
    $path = teatro_login_rl_path();
    @file_put_contents($path, json_encode($data), LOCK_EX);
}

/**
 * @return array{blocked:bool,retry_after?:int}
 */
function teatro_login_rl_status(int $maxAttempts = 5, int $windowSec = 900, int $blockSec = 900): array
{
    $now = time();
    $data = teatro_login_rl_read();

    if ($data['blocked_until'] > $now) {
        return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
    }

    // Ventana expirada: reiniciar contador
    if ($data['attempts'] > 0 && ($now - $data['first']) > $windowSec) {
        $data = ['attempts' => 0, 'first' => $now, 'blocked_until' => 0];
        teatro_login_rl_write($data);
    }

    return ['blocked' => false];
}

function teatro_login_rl_register_failure(int $maxAttempts = 5, int $windowSec = 900, int $blockSec = 900): void
{
    $now = time();
    $data = teatro_login_rl_read();

    if ($data['blocked_until'] > $now) {
        return;
    }
    if ($data['attempts'] <= 0 || ($now - $data['first']) > $windowSec) {
        $data = ['attempts' => 1, 'first' => $now, 'blocked_until' => 0];
    } else {
        $data['attempts']++;
    }

    if ($data['attempts'] >= $maxAttempts) {
        $data['blocked_until'] = $now + $blockSec;
    }
    teatro_login_rl_write($data);
}

function teatro_login_rl_clear(): void
{
    $path = teatro_login_rl_path();
    if (is_file($path)) {
        @unlink($path);
    }
}
