<?php

/**
 * Search term expansions for public business search.
 * Each key expands to related terms (OR-matched within the same intent group).
 *
 * @return array<string, list<string>>
 */
return [
    'clean' => ['cleaning', 'cleaner', 'cleaning services'],
    'cleaner' => ['clean', 'cleaning', 'cleaning services'],
    'cleaning' => ['clean', 'cleaner', 'cleaning services'],
    'plumb' => ['plumber', 'plumbing'],
    'plumber' => ['plumbing', 'plumb'],
    'plumbing' => ['plumber', 'plumb'],
    'electric' => ['electrical', 'electrician'],
    'electrician' => ['electrical', 'electric'],
    'electrical' => ['electrician', 'electric'],
    'fumigat' => ['fumigation', 'fumigation services'],
    'fumigation' => ['fumigat', 'fumigation services'],
    'repair' => ['repairs', 'fix', 'fixing'],
    'spa' => ['beauty', 'salon'],
    'salon' => ['beauty', 'spa'],
    'beauty' => ['salon', 'spa'],
];
