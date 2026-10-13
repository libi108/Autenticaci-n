<?php
require __DIR__.'/../src/security.php';
$n=0;
function check(bool $condition,string $label): void { global $n; if (!$condition) throw new RuntimeException($label); ++$n; }
$c=otp(); $hash=digest($c); $future=gmdate('Y-m-d H:i:s',time()+600);
check((bool)preg_match('/^[0-9]{6}$/',$c),'OTP format');
check(validCode($c,$hash,$future,0),'valid OTP');
check(!validCode('000000',$hash,$future,0),'wrong OTP');
check(!validCode($c,$hash,gmdate('Y-m-d H:i:s',time()-1),0),'expired OTP');
check(!validCode($c,$hash,$future,5),'attempt limit');
check(!validCode($c,null,null,0),'consumed OTP');
check(!validCode('1234567',$hash,$future,0),'malformed OTP');
check(!passwordValid('short'),'short password');
check(!passwordValid(str_repeat('a',73)),'bcrypt truncation');
$p='long-secure-password'; $h=password_hash($p,PASSWORD_BCRYPT);
check(password_verify($p,$h),'bcrypt roundtrip');
check(!password_verify('wrong-password',$h),'incorrect password');
echo "$n security checks passed\n";
