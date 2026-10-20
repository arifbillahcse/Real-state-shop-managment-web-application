<?php
require_once __DIR__ . '/_guard.php';

requireMethod('POST');
// Not requireStrictAdminApi(): the session's current role IS the
// impersonated user's role at this point, not admin. User::stopImpersonate()
// itself checks that an impersonator_id was actually stashed in the session.

$result = User::stopImpersonate();
if ($result === true) {
    jsonResponse(true, 'অ্যাডমিন হিসেবে ফিরে যাওয়া হয়েছে।');
}
jsonResponse(false, User::errorMessage($result));
