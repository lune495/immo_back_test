<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Compte,Locataire,DetailJournal,Outil,Journal,ClotureCaisse,DepenseProprio,CompteAgence,Proprietaire,CompteLocataire};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

use \PDF;

class JournalController extends Controller
{
    //
    private $queryName = "journals";

     public function save(Request $request)
    {
        try {
            return DB::transaction(function () use ($request)
            {
                DB::beginTransaction();
                $errors =null;
                $str_json = json_encode($request->detail_journals);
                $detail_journals = json_decode($str_json, true);
                $item = new Journal();
                $user = Auth::user();
                if (!empty($request->id))
                {
                    $item = Journal::find($request->id);
                }
                
                $item->solde = $request->solde;
                $item->user_id = $user->id;
                $item->save();
                // dd($detail_journals);
                foreach ($detail_journals as $detail) 
                {
                    $detail_journals = new DetailJournal();
                    if (isset($detail['locataire_id']))
                    {
                        $locataire = Locataire::where("id",$detail['locataire_id'])->get();
                        if(!$locataire->first())
                        {
                            $errors = "Ce locataire n'existe pas";
                        }
                    }
                    // if (empty($detail['libelle']))
                    // {
                    //     $errors = "Renseignez le libelle";
                    // }
                    if ($detail['entree'] == 0 && $detail['sortie'] == 0)
                    {
                        $errors = "veuillez préciser le type d'opération";
                    }
                    if (isset($detail['locataire_id']) && !isset($detail['date_location']))
                    {
                        $errors = "veuillez renseigner la date de location";
                    }
                    
                    if (!isset($errors))
                    {
                        // $depense = isset($detail['depense_proprio_id']) ? DepenseProprio::find($detail['depense_proprio_id']) : null;
                        $detail_journals->code = "JN0000{$item->id}";
                        $detail_journals->libelle = empty($detail['locataire_id']) ? $detail['libelle'] : "Paiement Loyer du {$detail['date_location']}";
                        $detail_journals->date_location = isset($detail['locataire_id']) ? $detail['date_location'] : null;
                        $detail_journals->entree = empty($detail['entree']) ? 0 : $detail['entree'];
                        $detail_journals->sortie = empty($detail['sortie']) ? 0 : $detail['sortie'];
                        $detail_journals->locataire_id = isset($detail['locataire_id']) ? $detail['locataire_id'] : null;
                        $detail_journals->proprietaire_id = isset($detail['proprietaire_id']) ? $detail['proprietaire_id'] : null;
                        $detail_journals->journal_id = $item->id;
                        $detail_journals->user_id = $user->id;
                        $detail_journals->save();
                        $saved = $detail_journals->save();
                        if($saved)
                        {
                            $id = $item->id; 
                            // ENTREE
                            if($detail['entree']!=0 && $detail['sortie'] ==0)
                            {
                                if(!empty($detail['locataire_id'])){
                                        $locataire_id = $detail['locataire_id'];
                                        $locataire = Locataire::find($locataire_id);
                                        if(!$locataire->resilier){
                                            $proprio_id = $locataire->bien_immo->proprietaire_id;
                                            $immo = $locataire->bien_immo; // Récupérer l'immeuble
                                            $commission_percentage = $immo->commission_agence ?? 0.07; // 7% par défaut si non spécifié
                                            $montant = $detail['entree'];
                                            // PAIEMENT COMPTE PROPRIETAIRE
                                            $compte_proprietaire = new Compte();
                                            $compte_proprietaire->libelle = "Paiement de `{$locataire->prenom}`";
                                            $compte_proprietaire->locataire_id = $locataire->id;
                                            $compte_proprietaire->proprietaire_id = $proprio_id;
                                            $compte_proprietaire->montant_compte = $detail['entree'];
                                            $compte_proprietaire->detail_journal_id = $detail_journals->id;
                                            $compte_proprietaire->save();
                                            // Calculer la commission
                                            $commission = $detail['entree'] * ($commission_percentage/100);
                                            // Créditer le compte de l'agence
                                            $compte_agence = new CompteAgence();
                                            $compte_agence->proprietaire_id = $locataire->bien_immo->proprietaire_id;
                                            $compte_agence->locataire_id = $locataire->id;
                                            $compte_agence->nature = "Honoraire pour paiement locataire `{$locataire->prenom}`";
                                            $compte_agence->commission = $commission * 0.18;
                                            $compte_agence->detail_journal_id = $detail_journals->id;
                                            $isSaved = $compte_agence->save();
                                            // DEPENSE HONORAIRE
                                            if ($isSaved) {
                                                $compte_proprietaire = new Compte();
                                                $compte_proprietaire->libelle = "Honoraire d'agence ($commission_percentage % de $montant)";
                                                $compte_proprietaire->proprietaire_id = $locataire->bien_immo->proprietaire_id;
                                                $compte_proprietaire->montant_compte = $commission;
                                                $compte_proprietaire->detail_journal_id = $detail_journals->id;
                                                $compte_proprietaire->save();
                                            }
                                            // Compte Locataire
                                            // $cc = $locataire->cc ? $locataire->cc : 0;
                                            $compte_locataire = new CompteLocataire();
                                            $compte_locataire->locataire_id = $locataire->id;
                                            $compte_locataire->libelle = "Paiement Location `{$detail['date_location']}`";
                                            $compte_locataire->dernier_date_paiement = isset($detail['date_location']) 
                                            ? Carbon::parse($detail['date_location'])->setTimeFromTimeString(Carbon::now()->toTimeString()) 
                                            : Carbon::now();
                                            $compte_locataire->debit = 0;
                                            $compte_locataire->credit = $detail['entree'];
                                            $compte_locataire->statut_paye = true;
                                            $compte_locataire->detail_journal_id = $detail_journals->id;
                                            $compte_locataire->save();
                                            $locataire->solde += $compte_locataire->debit - $compte_locataire->credit;
                                            $locataire->save();
                                    }else{
                                        $errors = "Opération Impossible le compte de `{$locataire->prenom}`est suspendu";
                                    }
                                }
                            }
                            // SORTIE
                            if($detail['entree'] ==0 && $detail['sortie'] !=0){
                                if(!empty($detail['proprietaire_id'])){
                                    $compte_proprietaire = new Compte();
                                    $compte_proprietaire->libelle = $detail['libelle'];
                                    $compte_proprietaire->proprietaire_id = $detail['proprietaire_id'];
                                    $compte_proprietaire->montant_compte = -1 * $detail['sortie'];
                                    $compte_proprietaire->detail_journal_id = $detail_journals->id;
                                    $compte_proprietaire->save();
                                }
                            }
                        }
                    }
                }
                if (isset($errors))
                {
                    throw new \Exception($errors);
                }
                DB::commit();
                return  Outil::redirectgraphql($this->queryName,"id:{$id}",Outil::$queries[$this->queryName]);
          });
        } catch (exception $e) {            
             DB::rollback();
             return $e->getMessage();
        }
    }

    public function generePDfGrandJournal($start = false, $end = false, $token)
    {
        // Chercher le token dans la base de données pour récupérer l'utilisateur correspondant
        $accessToken = PersonalAccessToken::findToken($token);
        // Vérifier si le token est valide
        if (!$accessToken || !$accessToken->tokenable) {
            return response()->json(['message' => 'Token invalide'], 401);
        }
        // Récupérer l'utilisateur associé au token
        $user = $accessToken->tokenable;

        // Initialiser la requête
        $query = DetailJournal::with('proprietaire', 'locataire');
        
        // Vérifier si des dates de début et de fin sont fournies et si l'utilisateur est authentifié
        if ($start && $end && $user->structure_id) {
            // Ajouter la condition pour filtrer par structure_id
            $query->where(function ($q) use ($user) {
                 // Filtrer par locataire associé à l'utilisateur
                $q->whereHas('locataire', function ($q) use ($user) {
                    $q->whereHas('user', function ($q) use ($user) {
                        $q->where('structure_id', $user->structure_id);
                    });
                })
                // Filtrer par propriétaire associé à l'utilisateur
                ->orWhereHas('proprietaire', function ($q) use ($user) {
                    $q->whereHas('user', function ($q) use ($user) {
                        $q->where('structure_id', $user->structure_id);
                    });
                });
            });
            // Convertir les dates pour inclure toute la journée
            $start = Carbon::parse($start)->startOfDay(); // 00:00:00
            $end = Carbon::parse($end)->endOfDay();       // 23:59:59

            // Appliquer le filtre entre les deux dates
            $query->whereBetween('created_at', [$start, $end]);
        }

        $query->where(function ($q) {
            $q->where('entree', '!=', 0)
              ->orWhere('sortie', '!=', 0);
        });
        // Récupérer les journaux filtrés
        $detailJournals = $query->get();

        // Préparer les données pour le PDF
        $data = [
            'detail_journals' => $detailJournals,
            'start' => date('d/m/y', strtotime($start)),
            'end' => date('d/m/y', strtotime($end)),
            'user' => $user,
        ];

        // Générer le PDF
        $pdf = PDF::loadView("pdf.grandjournalpdf", $data);
        return $pdf->stream();
    }
    


    public function produits()
    {
        $locataire = Locataire::all();
        return response()->json($locataire);

    }
    public function generatesituationparproprio($proprioId = null, $param3 = false, $token = null)
    {
         // Chercher le token dans la base de données pour récupérer l'utilisateur correspondant
         $accessToken = PersonalAccessToken::findToken($token);
         // Vérifier si le token est valide
         if (!$accessToken || !$accessToken->tokenable) {
             return response()->json(['message' => 'Token invalide'], 401);
         }
         // Récupérer l'utilisateur associé au token
         $user = $accessToken->tokenable;   

        if ($proprioId !== null && $user->structure_id) {
            $data = [];
            $proprietaire = Proprietaire::find($proprioId);
            $proprios = $proprietaire->bien_immos()->get();
            $nom = "$proprietaire->prenom $proprietaire->nom";

            // Requête pour obtenir les locataires, total crédit, total débit et solde
            $locataires = CompteLocataire::select(
                'locataires.id as locataire_id',
                'bien_immos.commission_agence',
                'bien_immos.nom_immeuble',
                DB::raw("CONCAT(locataires.prenom, ' ', locataires.nom) as nom_complet"),
                DB::raw('SUM(compte_locataires.debit) as total_debit'),
                DB::raw('SUM(compte_locataires.cc) as total_cc'),
                DB::raw('SUM(compte_locataires.credit) as total_credit'),
                DB::raw('SUM(compte_locataires.credit - compte_locataires.debit) as solde')
            )->join('locataires', 'compte_locataires.locataire_id', '=', 'locataires.id')
            ->join('bien_immos', 'locataires.bien_immo_id', '=', 'bien_immos.id')
            ->whereHas('detail_journal', function ($q) {
                $q->where('annule', false);})
            ->groupBy('locataires.id', 'locataires.prenom', 'locataires.nom','bien_immos.nom_immeuble','bien_immos.commission_agence');
            // dd($locataires);
            $startDate = null;
            $endDate = null;
            $month = false;

            // Vérifier si le paramètre est un mois (YYYY-MM)
            if ($param3 && preg_match('/^\d{4}-\d{2}$/', $param3)) {
                // Traiter comme un mois
                $month = $param3;
            }

            // Si un mois est fourni, le convertir en début et fin de mois
            if ($month !== false) {
                $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d H:i:s');
                $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d H:i:s');
                // Formater le mois en lettres (ex: SEPTEMBRE 2024)
                $data['mois'] = strtoupper(Carbon::createFromFormat('Y-m', $month)->locale('fr')->isoFormat('MMMM YYYY'));
            } else {
                // Utiliser le mois courant si aucun mois n'est fourni
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');
                $data['mois'] = strtoupper(Carbon::now()->locale('fr')->isoFormat('MMMM YYYY'));
            }

            // Requêtes pour les entrées et dépenses avec filtre de date
            $entreesQuery = Compte::where('proprietaire_id', $proprioId)
                ->where('locataire_id', '!=', null);

            $depensesQuery = Compte::where('proprietaire_id', $proprioId)
                ->where('locataire_id', '=', null)->where('montant_compte', '!=', 0)->where('montant_compte', '<', 0);

            $depense_honoraires = Compte::where('proprietaire_id', $proprioId)
                ->where('locataire_id', '=', null)->where('montant_compte', '!=', 0)->where('montant_compte', '>', 0);

            // Appliquer le filtre de date
            $entreesQuery->whereBetween('created_at', [$startDate, $endDate]);
            $depensesQuery->whereBetween('created_at', [$startDate, $endDate]);
            $depense_honoraires->whereBetween('created_at', [$startDate, $endDate]);
            $locataires->where('bien_immos.proprietaire_id', $proprioId)->whereBetween('compte_locataires.created_at', [$startDate, $endDate]);

            // Exécuter les requêtes
            $entrees = $entreesQuery->get();
            $depenses = $depensesQuery->get();
            $depense_honoraires = $depense_honoraires->get();
            $locataires = $locataires->get();
            $honoraire = 0;

            foreach ($depense_honoraires as $depense_honoraire) {
                $honoraire += $depense_honoraire->montant_compte;
            }

            // Préparer les données pour le PDF
            $data['nom_proprio'] = $nom;
            $data['locataires'] = $locataires;
            $data['sorties'] = $depenses;
            $data['honoraire'] = $honoraire;
            $data['proprios'] = $proprios;

            
            // Ajout du token dans les données pour validation ou suivi si nécessaire
            $data['user'] = $user;
            if($user->structure->id == 1){
                $pdf = PDF::loadView("pdf.situationproprio2", $data);
            }else {
                $pdf = PDF::loadView("pdf.situationproprio3", $data);
            }
            // Générer le PDF
            return $pdf->stream();
        } else {
            return view('notfound'); // Si l'ID du propriétaire n'est pas fourni
        }
    }






    public function closeCaisse(Request $request)
    {
        try {
            // Initialisation
            $errors = null;
            $montant = 0;
    
            // Vérifiez s'il existe une fermeture précédente
            $count = DB::table('cloture_caisses')->count();
    
            // Si aucune clôture précédente
            if ($count === 0) {
                // Calculer le montant total en prenant tous les journaux
                $detailJournals = DB::table('detail_journals')
                    ->select('libelle', DB::raw('SUM(entree) AS total_entree'))
                    ->groupBy('libelle')
                    ->orderBy('libelle')
                    ->get();
            } else {
                // Prendre les transactions après la dernière fermeture de caisse
                $detailJournals = DB::table('detail_journals')
                    ->select('libelle', DB::raw('SUM(entree) AS total_entree'))
                    ->where(function ($query) {
                        $query->where('created_at', '>=', function ($subQuery) {
                            $subQuery->select('date_fermeture')
                                ->from('cloture_caisses')
                                ->orderByDesc('date_fermeture')
                                ->limit(1);
                        });
                    })
                    ->where('created_at', '<=', now())
                    ->groupBy('libelle')
                    ->orderBy('libelle')
                    ->get();
            }
            // Calcul du montant total
            foreach ($detailJournals as $journal) {
                $montant += $journal->total_entree;
        }
            // Vérification si la caisse est vide
            if ($montant == 0) {
                $errors = "Vous ne pouvez pas clôturer une caisse vide.";
            }
    
            // Authentification de l'utilisateur
            $user = Auth::user();
            // Gestion des erreurs
            if (isset($errors)) {
                throw new \Exception('{"data": null, "errors": "'. $errors .'" }');
            }
    
            // Enregistrer la clôture de caisse
            $caisseCloture = new ClotureCaisse();
            $caisseCloture->date_fermeture = now(); // Utilisation de la date/heure actuelle
            $caisseCloture->montant_total = $montant;
            $caisseCloture->user_id = 1;
            // $caisseCloture->user_id = $user->id;
            $caisseCloture->save();
            return response()->json(['message' => 'Caisse fermée avec succès.']);
        } catch (\Throwable $e) {
            return $e->getMessage();
            // return response()->json(['error' => 'Une erreur est survenue lors de la clôture de la caisse.']);
        }
    }

    public function annulerpaimentloyer($id)
    {
        $detail_query = "detail_journals";
        $detail_journal =  DetailJournal::find($id);
        $detail_journal->annule = true;
        $detail_journal->save();
        $id = $detail_journal->id;
        return  Outil::redirectgraphql($detail_query,"id:{$id}",Outil::$queries[$detail_query]);
    }
    


    public function situationgeneralparproprio($id,$token = null)
    {
        // Chercher le token dans la base de données pour récupérer l'utilisateur correspondant
        $accessToken = PersonalAccessToken::findToken($token);
        // Vérifier si le token est valide
        if (!$accessToken || !$accessToken->tokenable) {
            return response()->json(['message' => 'Token invalide'], 401);
        }
        // Récupérer l'utilisateur associé au token
        $user = $accessToken->tokenable;

        if ($id !== null) {
            $data = [];
            $proprietaire = Proprietaire::findOrFail($id);

            // Récupérer les données des locataires avec la somme des crédits
            $locataires = DB::table('compte_locataires')
            ->join('locataires', 'compte_locataires.locataire_id', '=', 'locataires.id')
            ->join('bien_immos', 'locataires.bien_immo_id', '=', 'bien_immos.id')
            ->join('detail_journals', 'detail_journals.locataire_id', '=', 'locataires.id')
            ->where('bien_immos.proprietaire_id', $proprietaire->id)
            ->where('detail_journals.annule', false)
            ->select(
                'locataires.nom',
                'locataires.prenom',
                DB::raw('SUM(compte_locataires.debit) as total_debit'),
                DB::raw('SUM(compte_locataires.credit) as total_credit')
            )
            ->groupBy('locataires.id', 'locataires.nom', 'locataires.prenom')
            ->get();

            // Calculer le total des crédits pour ce propriétaire
            $totalCredits = $locataires->sum('total_credit');
            $totalDebits = $locataires->sum('total_debit');

            // Préparer les données pour la vue
            $data = [
                'nomProprietaire' => $proprietaire->nom,
                'prenomProprietaire' => $proprietaire->prenom,
                'totalCredits' => $totalCredits,
                'totalDebits' => $totalDebits,
                'locataires' => $locataires,
                'user' => $user
            ];
            $pdf = PDF::loadView("pdf.situationgeneralproprio",$data);
            return $pdf->stream();
        }else {
            return view('notfound'); // Si l'ID du locataire n'est pas fourni
        }
    }
}