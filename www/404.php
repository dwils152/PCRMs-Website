<?php
require_once dirname(__DIR__) . '/includes/layout.php';

pcrms_message_page(404, 'Page not found', 'Page not found',
    'The page you asked for is not here. It may have moved when the site was updated.', [
        ['Go to the home page', 'index.php'],
        ['Browse the CRM maps', 'browse_dataset.php'],
    ]);
