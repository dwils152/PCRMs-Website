<?php

// Legacy endpoint retained for old bookmarks. The supported gene workflows are
// now exposed through the dedicated analysis pages.
header('Location: detailed_search.php', true, 303);
exit;
