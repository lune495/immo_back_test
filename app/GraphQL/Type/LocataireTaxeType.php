<?php

namespace App\GraphQL\Type;

use Carbon\Carbon;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Illuminate\Support\Facades\DB;

class LocataireTaxeType extends GraphQLType
{
    protected $attributes =
    [
        'name' => 'LocataireTaxe',
        'description' => ''
    ];

    public function fields():array
    {
        return
        [
            'id'                                => ['type' => Type::int(), 'description' => ''],
            'locataire_id'                      => ['type' => Type::int()],
            'locataire'                         => ['type' => GraphQL::type('Locataire')],
            'taxe_id'                           => ['type' => Type::int()],
            'taxe'                              => ['type' => GraphQL::type('Taxe')],
            'valeur_taxe'                       => ['type' => Type::float()],
        ];
    }

    /*************** Pour les dates ***************/
    protected function resolveCreatedAtField($root, $args)
    {
        if (!isset($root['created_at']))
        {
            $date_at = $root->created_at;
        }
        else
        {
            $date_at = is_string($root['created_at']) ? $root['created_at'] : $root['created_at']->format(Outil::formatdate());
        }
        return $date_at;
    }
    protected function resolveCreatedAtFrField($root, $args)
    {
        if (!isset($root['created_at']))
        {
            $created_at = $root->created_at;
        }
        else
        {
            $created_at = $root['created_at'];
        }
        return Carbon::parse($created_at)->format('d/m/Y H:i:s');
    }

    protected function resolveUpdatedAtField($root, $args)
    {
        if (!isset($root['updated_at']))
        {
            $date_at = $root->updated_at;
        }
        else
        {
            $date_at = is_string($root['updated_at']) ? $root['updated_at'] : $root['updated_at']->format(Outil::formatdate());
        }
        return $date_at;
    }   

    protected function resolveUpdatedAtFrField($root, $args)
    {
        if (!isset($root['created_at']))
        {
            $date_at = $root->created_at;
        }
        else
        {
            $date_at = $root['created_at'];
        }
        return Carbon::parse($date_at)->format('d/m/Y H:i:s');
    }

    protected function resolveValeurTaxeField($root, $args)
    {
        return round($root->locataire->montant_loyer_ht * ($root->taxe->value/100));
    }
    
}
