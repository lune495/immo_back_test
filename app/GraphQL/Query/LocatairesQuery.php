<?php

namespace App\GraphQL\Query;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Support\Facades\GraphQL;
use App\Models\{Locataire,Outil};
use Illuminate\Support\Facades\Auth;

class LocatairesQuery extends Query
{
    protected $attributes = [
        'name' => 'locataires'
    ];

    public function type(): Type
    {
        return Type::listOf(GraphQL::type('Locataire'));
    }

    public function args(): array
    {
        return
        [
            'id'                  =>    ['type' => Type::int()],
            'code'                =>    ['type' => Type::string()],
            'nom'                 =>    ['type' => Type::string()],
            'prenom'              =>    ['type' => Type::string()],
            'search'              =>    ['type' => Type::string()],
            'bien_immo_id'        =>    ['type' => Type::int()],
        ];
    }

    public function resolve($root, $args)
    {
        $user = Auth::user();
        $query = Locataire::with('user');
        
        // Filtrer les propriétaires dont la structure_id de l'utilisateur associé correspond à celui de l'utilisateur connecté
        if ($user && $user->structure_id) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('structure_id', $user->structure_id);
            });
        }
    
        // Ajout des filtres selon les arguments
        if (isset($args['id'])) {
            $query->where('id', $args['id']);
        }

         // Ajout des filtres selon les arguments
         if (isset($args['bien_immo_id'])) {
            $query->where('bien_immo_id', $args['bien_immo_id']);
        }
    
        if (isset($args['search'])) {
            $query->where(function ($subQuery) use ($args) {
                $subQuery->where('code', Outil::getOperateurLikeDB(), '%' . $args['search'] . '%')
                         ->orWhere('nom', Outil::getOperateurLikeDB(), '%' . $args['search'] . '%')
                         ->orWhere('prenom', Outil::getOperateurLikeDB(), '%' . $args['search'] . '%');
            });
        }
    
        if (isset($args['nom'])) {
            $query->where('nom', Outil::getOperateurLikeDB(), '%' . $args['nom'] . '%');
        }
    
        if (isset($args['prenom'])) {
            $query->where('prenom', Outil::getOperateurLikeDB(), '%' . $args['prenom'] . '%');
        }
        
        // Trier les résultats
        $query->orderBy('id', 'desc');
    
        // Récupérer les résultats
        $locataires = $query->get();
    
        return $locataires->map(function (Locataire $item) {
            return [
                'id'                      => $item->id,
                'code'                    => $item->code,
                'nom'                     => $item->nom,
                'prenom'                  => $item->prenom,
                'cc'                      => $item->cc,
                'telephone'               => $item->telephone,
                'montant_loyer_ttc'       => $item->montant_loyer_ttc,
                'montant_loyer_ht'        => $item->montant_loyer_ht,
                'descriptif_loyer'        => $item->descriptif_loyer,
                'cni'                     => $item->CNI,
                'lieu_naissance'          => $item->lieu_naissance,
                'date_naissance'          => $item->date_naissance,
                'date_delivrance'         => $item->date_delivrance,
                'multipli'                => $item->multipli,
                'adresse_profession'      => $item->adresse_profession,
                'profession'              => $item->profession,
                'situation_matrimoniale'  => $item->situation_matrimoniale,
                'bien_immo_id'            => $item->bien_immo_id,
                'unite'                   => $item->unite,
                'caution'                 => $item->caution,
                'url_qr_code'             => $item->url_qr_code,
                'bien_immo'               => $item->bien_immo,
                'resilier'                => $item->resilier,
                'locataire_taxes'         => $item->locataire_taxes,
                'solde'                   => $item->solde,
                'email'                   => $item->email,
                'date_echeance_contrat'   => $item->date_echeance_contrat,
                'type_location'           => $item->type_location,
            ];
        });
    }
    
}
