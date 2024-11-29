<?php

namespace App\GraphQL\Type;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ProprioBienImmoPaginatedType extends GraphQLType
{
    protected $attributes =
    [
        'name' => 'proprio_bien_immospaginated'
    ];
    public function fields(): array
    {  return
        [
            'metadata' =>
            [
                'type' => GraphQL::type('Metadata'),
                'resolve' => function ($root)
                {
                    return array_except($root->toArray(), ['data']);
                }
            ],
            'data' =>
            [
                'type' => Type::listOf(GraphQL::type('ProprioBienImmo')),
                'resolve' => function ($root)
                {
                    return $root;
                }
            ]
        ];
    }
}
