<?php

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

return [
    'slugger' => null,

    'tag_model' => Tag::class,

    'taggable' => [
        'table_name' => 'taggables',
        'morph_name' => 'taggable',
        'class_name' => MorphPivot::class,
    ],
];
