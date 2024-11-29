<?php

namespace App\GraphQL\Query;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Illuminate\Support\Arr;
use \App\Models\{BienImmo,Outil};
use Illuminate\Support\Facades\Auth;

class BienImmoPaginatedQuery extends Query
{
    protected $attributes = [
        'name'              => 'bienimmospaginated',
        'description'       => ''
    ];

    public function type():type
    {
        return GraphQL::type('bienimmospaginated');
    }

    public function args():array
    {
        return
        [
            'id'                            => ['type' => Type::int()],
            'proprietaire_id'               => ['type' => Type::int()],
            'code'                          => ['type' => Type::string()],
        
            'page'                          => ['name' => 'page', 'description' => 'The page', 'type' => Type::int() ],
            'count'                         => ['name' => 'count',  'description' => 'The count', 'type' => Type::int() ]
        ];
    }


    public function resolve($root, $args)
    {
        $user = Auth::user();
        $query = BienImmo::query();

         // Filtrer les propriétaires dont la structure_id de l'utilisateur associé correspond à celui de l'utilisateur connecté
         if ($user && $user->structure_id) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('structure_id', $user->structure_id);
            });
        }
        if (isset($args['id']))
        {
            $query->where('id', $args['id']);
        }
        if (isset($args['proprietaire_id']))
        {
            $query = $query->where('proprietaire_id', $args['proprietaire_id']);
        }
        if (isset($args['code']))
        {
            $query->where('code',$args['code']);
        }
        $count = Arr::get($args, 'count', 10);
        $page  = Arr::get($args, 'page', 1);

        return $query->orderBy('id', 'desc')->paginate($count, ['*'], 'page', $page);
    }
}

