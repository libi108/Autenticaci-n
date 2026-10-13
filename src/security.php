<?php
declare(strict_types=1);
function otp(): string { return (string) random_int(100000, 999999); }
function digest(string $code): string { return hash('sha256', $code); }
function validCode(string $code, ?string $hash, ?string $expires, int $attempts): bool {
 return preg_match('/^[0-9]{6}$/D', $code) === 1 && $hash !== null && $expires !== null
  && strtotime($expires.' UTC') > time() && $attempts < 5 && hash_equals($hash, digest($code));
}
function passwordValid(string $password): bool { return strlen($password) >= 12 && strlen($password) <= 72; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
