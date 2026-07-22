<?php

declare(strict_types=1);

return [
    'initial_rating' => (int) env('ELO_INITIAL_RATING', 1000),
    'k_factor' => (int) env('ELO_K_FACTOR', 32),
];
