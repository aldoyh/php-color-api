<?php

declare(strict_types=1);
namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$colorType = new ObjectType([
    'name' => 'Color',
    'fields' => [
        'id' => Type::nonNull(Type::int()),
        'name' => Type::nonNull(Type::string()),
        'value' => Type::nonNull(Type::string()),
    ],
]);
