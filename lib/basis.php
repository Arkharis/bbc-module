<?php
/**
 * @link http://bbc.bitrix.expert
 * @copyright Copyright © 2014-2015 Nik Samokhvalov
 * @license MIT
 */

namespace Bex\Bbc;

use Bex\AdvancedComponent\PluginManager;
use Bex\AdvancedComponent\AdvancedComponentTrait;
use Bex\Plugins\CheckerPlugin;
use Bex\Plugins\ErrorNotifierPlugin;

/**
 * Abstraction basis component
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
abstract class Basis extends \CBitrixComponent
{
    use Traits\Common, AdvancedComponentTrait;

    /**
     * @var bool Auto executing methods of prolog / epilog in the traits
     */
    public $traitsAutoExecute = true;

    /**
     * @var array Used traits
     */
    private $usedTraits;

    /**
     * Executing methods prolog, getResult and epilog included traits
     *
     * @param string $type prolog, getResult or epilog
     */
    private function executeTraits($type)
    {
        if (empty($this->usedTraits))
        {
            return;
        }

        switch ($type)
        {
            case 'prolog':
                $type = 'Prolog';
            break;

            case 'main':
                $type = 'Main';
            break;

            default:
                $type = 'Epilog';
            break;
        }

        foreach ($this->usedTraits as $trait => $name)
        {
            $method = 'execute'.$type.$name;

            if (method_exists($trait, $method))
            {
                $this->$method();
            }
        }
    }

    /**
     * Set to $this->usedTraits included traits
     */
    private function readUsedTraits()
    {
        if ($this->traitsAutoExecute)
        {
            $reflection = new \ReflectionClass(get_called_class());

            $parentClass = $reflection;

            while (1)
            {
                foreach ($parentClass->getTraitNames() as $trait)
                {
                    $this->usedTraits[$trait] = bx_basename($trait);
                }

                if ($parentClass->name === __CLASS__)
                {
                    break;
                }

                $parentClass = $parentClass->getParentClass();
            }
        }
    }

    public function plugins()
    {
        return [
            'errorNotifier' => ErrorNotifierPlugin::getClass(),
            'checker' => CheckerPlugin::getClass(),
        ];
    }

    final protected function executeAdvancedComponent()
    {
        $this->pluginManager = new PluginManager($this, $this->plugins());

        $this->pluginManager->trigger('executeInit');

        $checker = CheckerPlugin::getInstance();

        $this->readUsedTraits();

        $checker->includeModules();
        $checker->checkParams();

        $this->startAjax();

        $this->executeTraits('prolog');
        $this->pluginManager->trigger('executeProlog');
        $this->executeProlog();

        if ($this->startCache())
        {
            $this->executeMain();
            $this->executeTraits('main');
            $this->pluginManager->trigger('executeMain');

            if ($this->cacheTemplate)
            {
                $this->render();
            }

            $this->writeCache();
        }

        if (!$this->cacheTemplate)
        {
            $this->render();
        }

        $this->executeTraits('epilog');
        $this->pluginManager->trigger('executeMain');
        $this->executeEpilog();

        $this->pluginManager->trigger('executeFinal');
    }

    public function executeComponent()
    {
        try {
            $this->executeAdvancedComponent();
        } catch (\Exception $e)
        {
            ErrorNotifierPlugin::getInstance()->catchException($e);
        }
    }
}