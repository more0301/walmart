<?php

namespace Walmart\core;

class Controller
{
    public $model;

    public function __construct()
    {
        $this->model = $this->setModel();
    }

    private function setModel()
    {
        $reflection = new \ReflectionClass(get_class($this));

        $model_name = str_replace(
            'Controller',
            'Model',
            $reflection->getShortName() ?? ''
        );

        $namespace = str_replace(
            'controllers',
            'models',
            $reflection->getNamespaceName() ?? ''
        );

        $model = $namespace . '\\' . $model_name;

        return new $model();
    }
}
