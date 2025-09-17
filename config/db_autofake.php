<?php

return [
    'locale' => 'ar_SA',
    'nullable_probability' => 0.1,
    'chunk' => 500,
    'ignore_columns' => ['id'],
    'timestamps' => [
        'created_at' => 'now',  // now|null|skip
        'updated_at' => 'now',
        'deleted_at' => 'null',
    ],
];
