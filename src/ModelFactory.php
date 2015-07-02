<?php
namespace Thedigital\Model_Template;

class ModelFactory
{
    // a map of model names to factory closures
    protected $map = [];

    public function __construct($map = [])
    {
        $this->map = $map;
    }

    public function newInstance($model_name, $context = [])
    {
        if (! isset($this->map[$model_name])) {
            throw new \Exception("$model_name not mapped");
        }
        $factory = $this->map[$model_name];
        $model = $factory();
        if (sizeof($context) > 0) {
            $model->setContext($context);
        }
        return $model;
    }
}
