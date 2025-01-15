<?php

declare(strict_types=1);
namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$colorType = new ObjectType([
    'name' => 'Color',
    'fields' => [
        'id' => Type::id(),
        'name' => Type::string(),
        'hex' => Type::string(),
        'rgb' => Type::string(),
        'created' => Type::string()
    ],
]);
