<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace SoliantConsulting\Apigility\Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZF\Configuration\ConfigResource;
use Zend\Config\Writer\PhpArray as PhpArrayWriter;
use Zend\Filter\FilterChain;

class AppController extends AbstractActionController
{
    public function indexAction()
    {
        $viewModel = new ViewModel;
        $viewModel->setTemplate('soliant-consulting/apigility/admin/app/index.phtml');

        return $viewModel;
    }

    public function createModuleAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->plugin('redirect')->toRoute('soliantconsulting-apigility-admin');
        }

        $moduleName = $this->getRequest()->getPost()->get('moduleName');
        if (!$moduleName) {
            throw new \Exception('Invalid or missing module name');
        }

        $moduleResource = $this->getServiceLocator()->get('ZF\Apigility\Admin\Model\ModuleResource');
        $moduleResource->setModulePath(realpath(__DIR__ . '/../../../../../../../../'));

        $metadata = $moduleResource->create(array(
            'name' =>  $moduleName,
        ));

        // Set renderer defaults
        $patchConfig = array(
            'service_manager' => array(
                'abstract_factories' => array(
                    'SoliantConsulting\Apigility\Server\Resource\DoctrineResourceFactory',
                    'SoliantConsulting\Apigility\Server\Hydrator\DoctrineHydratorFactory',
                ),
            ),
        );

        $config = $this->getServiceLocator()->get('Config');
        $writer = new PhpArrayWriter();
        $moduleConfig = new ConfigResource($config, 'module/' . $moduleName . '/config/module.config.php', $writer);

        $moduleConfig->patch($patchConfig, true);

        $this->plugin('redirect')->toRoute('soliantconsulting-apigility-admin-select-entities',
            array(
                'moduleName' => $moduleName,
                'objectManagerAlias' => 'doctrine.entitymanager.orm_default'
            )
        );
    }

    public function selectEntitiesAction()
    {
        $moduleName = $this->params()->fromRoute('moduleName');
        $objectManagerAlias = $this->params()->fromRoute('objectManagerAlias');
        if (!$moduleName or !$objectManagerAlias) {
            throw new \Exception('Invalid or missing module name or objectManagerAlias');
        }

        $viewModel = new ViewModel;
        $viewModel->setTemplate('soliant-consulting/apigility/admin/app/select-entities.phtml');

        try {
            $objectManager = $this->getServiceLocator()->get($objectManagerAlias);
            $metadataFactory = $objectManager->getMetadataFactory();

            $viewModel->setVariable('allMetadata', $metadataFactory->getAllMetadata());
        } catch (\Exception $e) {
            $viewModel->setVariable('invalidObjectManager', true);
        }

        $viewModel->setVariable('moduleName', $moduleName);
        $viewModel->setVariable('objectManagerAlias', $objectManagerAlias);

        return $viewModel;
    }

    public function createResourcesAction()
    {
        $moduleName = $this->params()->fromRoute('moduleName');
        $objectManagerAlias = $this->params()->fromPost('objectManagerAlias');
        if (!$moduleName or !$objectManagerAlias) {
            throw new \Exception('Invalid or missing module name or object manager alias');
        }

        $entitiyClassNames = $this->params()->fromPost('entityClassName');
        if (!sizeof($entitiyClassNames)) {
            throw new \Exception('No entities selected to Apigility-enable');
        }

        // Get the route prefix and remove any / from ends of string
        $routePrefix = $this->params()->fromPost('routePrefix');
        if (!$routePrefix) {
            $routePrefix = '';
        } else {
            while(substr($routePrefix, 0, 1) == '/') {
                $routePrefix = substr($routePrefix, 1);
            }

            while(substr($routePrefix, strlen($routePrefix) - 1) == '/') {
                $routePrefix = substr($routePrefix, 0, strlen($routePrefix) - 1);
            }
        }

        $useEntityNamespacesForRoute = (boolean)$this->params()->fromPost('useEntityNamespacesForRoute');

        $objectManager = $this->getServiceLocator()->get($objectManagerAlias);
        $metadataFactory = $objectManager->getMetadataFactory();

         if (class_exists('\\Doctrine\\ORM\\EntityManager') && $objectManager instanceof \Doctrine\ORM\EntityManager) {
            $doctrineHydrator = 'DoctrineORMModule\\Stdlib\\Hydrator\\DoctrineEntity';
         } elseif (class_exists('\\Doctrine\\ODM\\MongoDB\\DocumentManager') && $objectManager instanceof \Doctrine\ODM\MongoDB\DocumentManager) {
            $doctrineHydrator = $this->params()->fromPost('doctrineEntity');
            if (!class_exists($doctrineHydrator)) {
                return new ApiProblem(500, "Invalid doctrine entity");
            }
         } else {
             return new ApiProblem(500, 'No valid doctrine module is found for objectManager ' . get_class($objectManager));
         }

        $serviceResource = $this->getServiceLocator()->get('SoliantConsulting\Apigility\Admin\Model\DoctrineRestServiceResource');

        // Generate a session id for results on next page
        session_start();
        $results = md5(uniqid());

        foreach ($metadataFactory->getAllMetadata() as $entityMetadata) {
            if (!in_array($entityMetadata->name, $entitiyClassNames)) continue;

            $resourceName = substr($entityMetadata->name, strlen($entityMetadata->namespace) + 1);

            if (sizeof($entityMetadata->identifier) !== 1) {
                throw new \Exception($entityMetadata->name . " does not have exactly one identifier and cannot be generated");
            }

            $filter = new FilterChain();
            $filter->attachByName('WordCamelCaseToUnderscore')
                   ->attachByName('StringToLower');

            if ($useEntityNamespacesForRoute) {
                $route = '/' . $routePrefix . '/' . $filter(str_replace('\\', '/', $entityMetadata->name));
            } else {
                $route = '/' . $routePrefix . '/' . $filter($resourceName);
            }

            $hydratorName = substr($entityMetadata->name, strlen($entityMetadata->namespace) + 1);

            $hydratorName = $moduleName . '\\V1\\Rest\\' . $resourceName . '\\' . $resourceName . 'Hydrator';

            $serviceResource->setModuleName($moduleName);
            $serviceResource->create(array(
                'objectManager' => $objectManagerAlias,
                'resourcename' => $resourceName,
                'entityClass' => $entityMetadata->name,
                'pageSizeParam' => 'limit',
                'identifierName' => array_pop($entityMetadata->identifier),
                'routeMatch' => $route,
                'hydratorName' => $hydratorName,
                'doctrineHydrator' => $doctrineHydrator,
            ));

            $_SESSION[$results][$entityMetadata->name] = $route;
        }

#print_r($_SESSION[$results]);die('asdf');
        return $this->plugin('redirect')->toRoute('soliantconsulting-apigility-admin-done', array('moduleName' => $moduleName, 'results' => $results));
    }

    public function doneAction() {
        $moduleName = $this->params()->fromRoute('moduleName');
        if (!$moduleName) {
            throw new \Exception('Invalid or missing module name');
        }

        session_start();
        $results = $this->params()->fromRoute('results');

        $viewModel = new ViewModel;
        $viewModel->setTemplate('soliant-consulting/apigility/admin/app/done.phtml');
        $viewModel->setVariable('moduleName', $moduleName);

        $viewModel->setVariable('results', $_SESSION[$results]);

        return $viewModel;
    }
}
