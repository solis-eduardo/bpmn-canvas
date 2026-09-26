<?php

return [
    // How many days before a Share's expires_at the Diagram Owner is
    // warned by `shares:warn-expiring`.
    'expiry_warning_lead_days' => (int) env('SHARE_EXPIRY_WARNING_LEAD_DAYS', 2),
];
