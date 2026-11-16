<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$api = file_get_contents($root.'admin/api.php');
$client = file_get_contents($root.'admin-ui-source/src/api/qifu.ts');
$auth = file_get_contents($root.'admin-ui-source/src/api/auth.ts');
$guard = file_get_contents($root.'admin-ui-source/src/router/guards/beforeEach.ts');
$app = file_get_contents($root.'admin-ui-source/src/App.vue');
$common = file_get_contents($root.'includes/common.php');
$member = file_get_contents($root.'includes/member.php');
$security = file_get_contents($root.'includes/security.php');
$userStore = file_get_contents($root.'admin-ui-source/src/store/modules/user.ts');
$loginView = file_get_contents($root.'admin-ui-source/src/views/auth/login/index.vue');
$failures = array();

function check_session_restore($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_session_restore(strpos($api, "if(\$action === 'session_status')") !== false, 'public session status endpoint is missing');
check_session_restore(strpos($api, "'authenticated'=>\$authenticated") !== false, 'session endpoint does not expose authentication state');
check_session_restore(strpos($api, "'user'=>\$authenticated ? qifu_api_user() : null") !== false, 'session endpoint exposes a user outside an authenticated session');
check_session_restore(strpos($client, 'export function qifuSessionStatus()') !== false, 'session status API client is missing');
check_session_restore(strpos($auth, 'export function fetchSessionStatus()') !== false, 'auth API does not expose session restoration');
check_session_restore(strpos($guard, 'await restoreServerSession(userStore)') !== false, 'route guard does not restore the PHP session');
check_session_restore(strpos($guard, "userStore.setToken('session')") !== false && strpos($guard, 'userStore.setLoginStatus(true)') !== false, 'restored session does not update the user store');
check_session_restore(strpos($guard, 'userStore.isLogin && to.path === RoutesAlias.Login') !== false, 'authenticated users can still remain on the login page');
check_session_restore(strpos($common, "define('QIFU_ADMIN_SESSION_IDLE_TIMEOUT', 43200)") !== false, 'backend session timeout is not set to 12 hours');
check_session_restore(strpos($common, "ini_set('session.gc_maxlifetime', (string)QIFU_ADMIN_SESSION_IDLE_TIMEOUT)") !== false, 'PHP garbage collection can expire sessions before the application timeout');
check_session_restore(strpos($common, "ini_set('session.gc_probability', '1')") !== false && strpos($common, "ini_set('session.gc_divisor', '100')") !== false, 'isolated administrator sessions have no garbage collection policy');
check_session_restore(strpos($common, "'qifu-sessions-'") !== false && strpos($common, 'ini_set(\'session.save_path\', $session_root)') !== false, 'administrator sessions still share the global PHP session directory');
check_session_restore(strpos($member, 'time() - $last_seen <= $session_idle_timeout') !== false && strpos($member, '<= 7200') === false, 'administrator activity still expires after two hours');
check_session_restore(strpos($app, 'SESSION_HEARTBEAT_INTERVAL = 5 * 60 * 1000') !== false, 'visible administrator pages do not renew the server session');
check_session_restore(strpos($app, 'document.visibilityState') !== false && strpos($app, 'await fetchSessionStatus()') !== false, 'session heartbeat is not visibility-aware');
check_session_restore(strpos($security, "define('QIFU_ADMIN_REMEMBER_LIFETIME', 2592000)") !== false, '30-day remember-login lifetime is missing');
check_session_restore(strpos($security, 'function qifu_admin_remember_login()') !== false, 'remember-login session restore helper is missing');
check_session_restore(strpos($member, 'qifu_admin_remember_login()') !== false, 'admin bootstrap does not restore a remember-login session');
check_session_restore(strpos($api, 'qifu_admin_remember_issue($username)') !== false, 'login endpoint does not issue a remember-login token');
check_session_restore(strpos($api, 'qifu_admin_remember_revoke_current()') !== false, 'logout endpoint does not revoke the remember-login token');
check_session_restore(strpos($userStore, "fetch('./api.php?action=logout'") !== false, 'frontend logout does not call the backend logout endpoint');
check_session_restore(strpos($loginView, 'remember: formData.rememberPassword') !== false, 'login form does not send the remember-login option');
check_session_restore(strpos($loginView, 'rememberPassword: true') !== false, 'remember-login option is not enabled by default');

if($failures){
    fwrite(STDERR, "Admin session restore tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin session restore tests passed.\n";
?>
