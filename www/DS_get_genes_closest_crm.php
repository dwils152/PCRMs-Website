<?php

// The analysis searches are now tabs on one page; keep old links working.
header('Location: detailed_search.php?tool=closest', true, 301);
exit;
