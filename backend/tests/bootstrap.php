<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Force test env in ALL three slots before Dotenv reads any of them.
// Container OS env (APP_ENV=prod) populates $_ENV via variables_order=EGPCS;
// KernelTestCase checks $_ENV before $_SERVER, so prod would win without this.
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
putenv('APP_ENV=test');

// Email verification enforcement is a runtime env var too: the container may
// set REQUIRE_EMAIL_VERIFICATION=true, which would otherwise win over .env.test
// and break every E2E test that logs an unverified user in.
$_ENV['REQUIRE_EMAIL_VERIFICATION'] = 'false';
$_SERVER['REQUIRE_EMAIL_VERIFICATION'] = 'false';
putenv('REQUIRE_EMAIL_VERIFICATION=false');

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
