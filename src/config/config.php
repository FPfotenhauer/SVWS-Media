<?php

declare(strict_types=1);

const APP_NAME = 'SVWS-Media';
const BASE_PATH = __DIR__ . '/../../';
const DATA_PATH = BASE_PATH . 'data/';
const DB_PATH = DATA_PATH . 'database.sqlite';

if (!defined('SVWS_BASE_URL')) {
	define('SVWS_BASE_URL', getenv('SVWS_BASE_URL') !== false ? (string) getenv('SVWS_BASE_URL') : 'https://localhost:8443');
}
if (!defined('SVWS_SCHEMA')) {
	define('SVWS_SCHEMA', getenv('SVWS_SCHEMA') !== false ? (string) getenv('SVWS_SCHEMA') : '');
}
if (!defined('SVWS_ID_LERNPLATTFORM')) {
	define('SVWS_ID_LERNPLATTFORM', getenv('SVWS_ID_LERNPLATTFORM') !== false ? (int) getenv('SVWS_ID_LERNPLATTFORM') : 1);
}
if (!defined('SVWS_ID_SCHULJAHRESABSCHNITT')) {
	define('SVWS_ID_SCHULJAHRESABSCHNITT', getenv('SVWS_ID_SCHULJAHRESABSCHNITT') !== false ? (int) getenv('SVWS_ID_SCHULJAHRESABSCHNITT') : 1);
}
if (!defined('SVWS_VERIFY_TLS')) {
	$verifyTlsValue = getenv('SVWS_VERIFY_TLS');
	define('SVWS_VERIFY_TLS', $verifyTlsValue !== false && in_array(strtolower((string) $verifyTlsValue), ['1', 'true', 'yes'], true));
}
if (!defined('SVWS_USERNAME')) {
	define('SVWS_USERNAME', getenv('SVWS_USERNAME') !== false ? (string) getenv('SVWS_USERNAME') : 'Admin');
}
if (!defined('SVWS_PASSWORD')) {
	define('SVWS_PASSWORD', getenv('SVWS_PASSWORD') !== false ? (string) getenv('SVWS_PASSWORD') : '');
}
