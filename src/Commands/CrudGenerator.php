<?php

namespace Tablar\CrudGenerator\Commands;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;

/**
 * Class CrudGenerator.
 *
 * @author  Awais <asargodha@gmail.com>
 */
class CrudGenerator extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud
                            {name : Table name}
                            {--route= : Custom route name}
                            {--crud-name= : Custom crud name}
                            {--lang= : language}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create bootstrap CRUD operations';

    /**
     * Options of the console command.
     *
     * @var array
     */
    protected $crudOptions = [];

    /**
     * Execute the console command.
     *
     * @return bool|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    public function handle()
    {
        $this->info('Running Crud Generator ...');

        $this->crudOptions = $this->buildOptions()->options();

        $this->table = $this->getNameInput();

        // If table not exist in DB return
        if (!$this->tableExists()) {
            $this->error("`{$this->table}` table not exist");

            return false;
        }

        // set language
        $this->_setLanguage();

        // Build the class name from table name
        $this->name = $this->_buildClassName();

        // Build the route name
        $this->routeName = $this->_buildRouteName(); 

        // Generate the crud
        $this->buildOptions()
            ->buildController()
            ->buildModel()
            ->buildViews()
            ->buildRoute();

        $this->info('Created Successfully.');

        return true;
    }

    /**
     * Build the Controller Class and save in app/Http/Controllers.
     *
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildController()
    {
        $controllerPath = $this->_getControllerPath($this->name);

        if ($this->files->exists($controllerPath) && $this->ask('Already exist Controller. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Controller ...');

        $replace = $this->buildReplacements();

        $controllerTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('Controller')
        );

        $this->write($controllerPath, $controllerTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildModel()
    {
        $modelPath = $this->_getModelPath($this->name);

        if ($this->files->exists($modelPath) && $this->ask('Already exist Model. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Model ...');

        // Make the models attributes and replacement
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

        $modelTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('Model')
        );

        $this->write($modelPath, $modelTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @throws \Exception
     */
    protected function buildViews()
    {
        $this->info('Creating Views ...');

        $tableHead = "\n";
        $tableBody = "\n";
        $viewRows = "\n";
        $form = "\n";

        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));

            $tableHead .= $this->getHead($title);
            $tableBody .= $this->getBody($column);
            $viewRows .= $this->getField($title, $column, 'view-field');
            $form .= $this->getField($title, $column, 'form-field');
        }

        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeader}}' => $tableHead,
            '{{tableBody}}' => $tableBody,
            '{{viewRows}}' => $viewRows,
            '{{form}}' => $form,
        ]);

        $this->buildLayout();

        foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
            $viewTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub("views/{$view}")
            );

            $this->write($this->_getViewPath($view), $viewTemplate);
        }

        return $this;
    }

    /**
     * Build the Controller Class and save in app/Http/Controllers.
     *
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildRoute()
    {       
        $route = "Route::resource('/";
        $route .= $this->routeName . "', ";
        $route .= $this->controllerNamespace ."\\". $this->name;
        $route .= 'Controller::class);';
      
        $routesPath = base_path("routes/web.php");

        $this->files->append($routesPath, $route . PHP_EOL); 

        $this->info('Creating Route ...');

        return $this;
    }

    /**
     * Make the class name from table name.
     *
     */
    private function _setLanguage()
    {        
        if($this->crudOptions['lang']){
            Pluralizer::useLanguage($this->crudOptions['lang']);
        } 
    }

    /**
     * Make the class name from table name.
     *
     * @return string
     */
    private function _buildRouteName()
    {       
        if($this->crudOptions['route']){
            return $this->crudOptions['route'];
        } 
        return strtolower($this->name);
    }

    /**
     * Make the class name from table name.
     *
     * @return string
     */
    private function _buildClassName()
    {
        if($this->crudOptions['crud-name']){
            return ucfirst($this->crudOptions['crud-name']);
        }
        return Str::studly(Str::singular($this->table));
    }
}
