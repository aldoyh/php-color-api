<?php

declare(strict_types=1);
namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$colorType = new ObjectType([
    'name' => 'Color',
    'fields' => [
        'hex' => Type::nonNull(Type::string()),
        'rgb' => Type::nonNull(Type::string()),
        'hsl' => Type::nonNull(Type::string()),
        'name' => Type::string()
    ],
]);
