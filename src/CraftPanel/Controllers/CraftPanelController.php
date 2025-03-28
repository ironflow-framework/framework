<?php

namespace IronFlow\CraftPanel\Controllers;

use IronFlow\Http\Controller;
use IronFlow\Http\Request;
use IronFlow\Http\Response;
use IronFlow\Support\Flash;
use IronFlow\Support\Utils\Str;
use IronFlow\Validation\Validator;
use IronFlow\Support\Collection;
use IronFlow\Support\Config;
use IronFlow\Support\Paginator;
use IronFlow\Support\Excel;

class CraftPanelController extends Controller
{
    /**
     * Modèle actuellement géré
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * Configuration du modèle
     * @var array
     */
    protected array $config = [
        'perPage' => 15,
        'sortable' => true,
        'searchable' => true,
        'exportable' => true,
    ];

    /**
     * Affiche le tableau de bord
     * @return Response
     */
    public function dashboard(): Response
    {
        $stats = $this->getDashboardStats();
        $models = $this->getAdminModels();

        return $this->view('CraftPanel::dashboard', [
            'stats' => $stats,
            'models' => $models,
            'title' => Config::get('craftpanel.title'),
        ]);
    }

    /**
     * Affiche la liste des éléments d'un modèle
     * @param string $model Nom du modèle
     * @return Response
     */
    public function index(string $model): Response
    {
        $this->checkModelPermission($model, 'view');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $items = $this->getItems($modelClass);
        $fields = $modelClass::getFormFields();
        $filters = $modelClass::getFilters();

        return $this->view('CraftPanel::index', [
            'model' => $model,
            'modelClass' => $modelClass,
            'items' => $items,
            'fields' => $fields,
            'filters' => $filters,
            'title' => $modelClass::getDisplayName(),
        ]);
    }

    /**
     * Affiche le formulaire de création
     * @param string $model Nom du modèle
     * @return Response
     */
    public function create(string $model): Response
    {
        $this->checkModelPermission($model, 'create');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $fields = $modelClass::getFormFields();

        return $this->view('CraftPanel::create', [
            'model' => $model,
            'modelClass' => $modelClass,
            'fields' => $fields,
            'title' => "Créer " . $modelClass::getDisplayName(),
        ]);
    }

    /**
     * Enregistre un nouvel élément
     * @param string $model Nom du modèle
     * @return Response
     */
    public function store(Request $request, string $model): Response
    {
        $data = $request->all();
        $this->checkModelPermission($model, 'create');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $validator = Validator::make($data, $modelClass::getValidationRules());

        if ($validator->validate()) {
            return $this->back()->with('errors', $validator->errors());
        }

        $item = $modelClass::create($data);
        logger()->info("Nouvel élément créé dans le modèle {$model}", ['id' => $item->id]);
        Flash::success('L\'élément a été créé avec succès.');

        return $this->redirect('craftpanel.index')->with('model', $model);
    }

    /**
     * Affiche le formulaire d'édition
     * @param string $model Nom du modèle
     * @param int $id Identifiant de l'élément
     * @return Response
     */
    public function edit(string $model, int $id): Response
    {
        $this->checkModelPermission($model, 'edit');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $item = $modelClass::find($id);
        $fields = $modelClass::getFormFields();

        return $this->view('CraftPanel::edit', [
            'model' => $model,
            'modelClass' => $modelClass,
            'item' => $item,
            'fields' => $fields,
            'title' => "Modifier " . $modelClass::getDisplayName(),
        ]);
    }

    /**
     * Met à jour un élément
     * @param Request $request
     * @param string $model Nom du modèle
     * @param int $id Identifiant de l'élément
     * @return Response
     */
    public function update(Request $request, string $model, int $id): Response
    {
        $data = $request->all();
        $this->checkModelPermission($model, 'edit');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $item = $modelClass::find($id);
        $validator = Validator::make($data, $modelClass::getValidationRules());

        if ($validator->validate()) {
            return $this->back()->with('errors', $validator->errors());
        }

        $oldData = $item->toArray();
        $item->update($data);
        logger()->info("Élément mis à jour dans le modèle {$model}", [
            'id' => $id,
            'changes' => array_diff_assoc($item->toArray(), $oldData)
        ]);
        Flash::success('L\'élément a été mis à jour avec succès.');

        return $this->redirect('craftpanel.index', ['model' => $model]);
    }

    /**
     * Supprime un élément
     * @param string $model Nom du modèle
     * @param int $id Identifiant de l'élément
     * @return Response
     */
    public function destroy(string $model, int $id): Response
    {
        $this->checkModelPermission($model, 'delete');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $item = $modelClass::find($id);
        $item->delete();
        logger()->info("Élément supprimé du modèle {$model}", ['id' => $id]);
        Flash::success('L\'élément a été supprimé avec succès.');

        return $this->redirect('craftpanel.index', ['model' => $model]);
    }

    /**
     * Affiche les paramètres
     * @return Response
     */
    public function settings(): Response
    {
        return $this->view('CraftPanel::settings', [
            'title' => 'Paramètres',
        ]);
    }

    /**
     * Met à jour les paramètres
     * @return Response
     */
    public function updateSettings(): Response
    {
        // TODO: Implémenter la mise à jour des paramètres
        return $this->redirect('craftpanel.settings');
    }

    /**
     * Vérifie les permissions du modèle
     * @param string $model Nom du modèle
     * @param string $action Action à vérifier
     * @return void
     */
    protected function checkModelPermission(string $model, string $action): void
    {
        if (!auth()->check()) {
            throw new \Exception('Unauthorized access');
        }

        $permissions = Config::get('craftpanel.permissions', []);
        $requiredPermission = $permissions[$model][$action] ?? "{$model}.{$action}";

        if (!auth()->user()->can($requiredPermission)) {
            throw new \Exception("Permission denied for action '$action' on model '$model'");
        }
    }

    /**
     * Vérifie l'existence du modèle
     * @param string $model
     * @return void
     * @throws \Exception
     */
    protected function checkModelExists(string $model): void
    {
        if (!in_array($model, $this->getAdminModels())) {
            throw new \Exception("Model '$model' is not registered in CraftPanel");
        }
    }

    /**
     * Récupère la liste des modèles administrables
     * @return array
     */
    protected function getAdminModels(): array
    {
        return Config::get('craftpanel.models', []);
    }

    /**
     * Récupère la classe du modèle
     * @param string $model
     * @return string
     */
    protected function getModelClass(string $model): string
    {
        $namespace = Config::get('craftpanel.model_namespace', 'App\Models');
        return Str::studly($namespace . '\\' . $model);
    }

    /**
     * Récupère les éléments du modèle avec filtrage et pagination
     * @param string $modelClass
     * @return Collection|Paginator
     */
    protected function getItems(string $modelClass)
    {
        $query = $modelClass::query();
        $request = new Request();

        // Gestion des relations
        $relations = $modelClass::getRelations();
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Gestion des filtres
        $filters = $modelClass::getFilters();
        foreach ($filters as $field => $filter) {
            if ($request->has($field)) {
                $query->where($field, $request->get($field));
            }
        }

        // Gestion du tri
        if ($this->config['sortable'] && $request->has('sort')) {
            $query->orderBy($request->get('sort'), $request->get('direction', 'asc'));
        }

        // Gestion de la recherche
        if ($this->config['searchable'] && $request->has('search')) {
            $search = $request->get('search');
            $searchFields = $modelClass::getSearchableFields();
            $query->where(function ($query) use ($search, $searchFields) {
                foreach ($searchFields as $field) {
                    $query->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        // Pagination
        return $query->paginate($this->config['perPage']);
    }

    /**
     * Exporte les données du modèle
     * @param string $model
     * @return Response
     */
    public function export(string $model): Response
    {
        $this->checkModelPermission($model, 'export');
        $this->checkModelExists($model);

        $modelClass = $this->getModelClass($model);
        $items = $modelClass::all();
        $fields = $modelClass::getFormFields();

        $data = [];
        foreach ($items as $item) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = $item->{$field};
            }
            $data[] = $row;
        }

        return Excel::download($data, "{$model}_export_" . date('Y-m-d') . '.xlsx');
    }

    /**
     * Récupère les statistiques du tableau de bord
     * @return array
     */
    protected function getDashboardStats(): array
    {
        $stats = [];
        foreach ($this->getAdminModels() as $model) {
            $modelClass = $this->getModelClass($model);
            $stats[$model] = [
                'count' => $modelClass::count(),
                'name' => $modelClass::getDisplayName(),
            ];
        }
        return $stats;
    }
}
