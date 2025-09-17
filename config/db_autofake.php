<?php

return [
    'locale' => 'en_USA',
    'nullable_probability' => 0.1,
    'chunk' => 500,
    'ignore_columns' => ['id'],
    'timestamps' => [
        'created_at' => 'now',  // now|null|skip
        'updated_at' => 'now',
        'deleted_at' => 'null',
        'email_verified_at' => 'null', 

    ],
];
