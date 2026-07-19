#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Stratum CMS — one-time (or key-rotation) update signing keypair generator.
 *
 * Generates an Ed25519 keypair via libsodium. The PUBLIC key is written into
 * core/update-public.key — it ships with every Stratum install (committed,
 * deployed, safe to be public) and is what SystemUpdateController verifies
 * incoming update packages against. The PRIVATE key is written ONLY to the
 * path you specify on the command line — never inside this project tree,
 * never committed, never deployed anywhere. Whoever holds the private key
 * can sign an update package that every Stratum install will accept and
 * execute; treat it exactly that seriously. Losing it means you can no
 * longer ship signed updates under the current public key (see --force
 * below); leaking it means every deployed Stratum site is compromised.
 *
 * Usage: php bin/generate-update-keypair.php /secure/path/outside/this/repo/update-private.key
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$rootDir = dirname(__DIR__);
$publicKeyPath = $rootDir . '/core/update-public.key';

$privateKeyPath = $argv[1] ?? null;
$force = in_array('--force', $argv, true);

if ($privateKeyPath === null || $privateKeyPath === '') {
    fwrite(STDERR, "Usage: php bin/generate-update-keypair.php <path-to-write-private-key> [--force]\n");
    fwrite(STDERR, "The path must be OUTSIDE this project directory — it is never committed or deployed.\n");
    exit(1);
}

if (str_starts_with(realpath(dirname($privateKeyPath)) ?: $privateKeyPath, $rootDir)) {
    fwrite(STDERR, "Refusing: that path is inside this project. The private key must never live in the deployed tree.\n");
    exit(1);
}

if (is_file($publicKeyPath) && !$force) {
    fwrite(STDERR, "core/update-public.key already exists. Regenerating invalidates every previously-signed\n");
    fwrite(STDERR, "update package and every already-deployed site's ability to verify new ones until they\n");
    fwrite(STDERR, "receive this new public key some other way. Pass --force if you really mean to rotate keys.\n");
    exit(1);
}

$keypair = sodium_crypto_sign_keypair();
$publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
$secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
sodium_memzero($keypair);

file_put_contents($publicKeyPath, $publicKey . "\n");

if (!is_dir(dirname($privateKeyPath))) {
    mkdir(dirname($privateKeyPath), 0700, true);
}
file_put_contents($privateKeyPath, $secretKey . "\n");
chmod($privateKeyPath, 0600);

fwrite(STDOUT, "Public key written to: {$publicKeyPath} (commit this, it ships with the app)\n");
fwrite(STDOUT, "Private key written to: {$privateKeyPath} (keep this OFFLINE — never commit, never deploy)\n");
