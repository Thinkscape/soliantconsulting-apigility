<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace SoliantConsulting\Apigility\Admin\Model;

use ZF\Rest\Exception\CreationException;
use ZF\Apigility\Admin\Model\NewRestServiceEntity as ZFNewRestServiceEntity;

class NewRestServiceEntity extends ZFNewRestServiceEntity
{
    protected $objectManager;

    protected $hydratorName;

    public function exchangeArray(array $data)
    {
        parent::exchangeArray($data);
        foreach ($data as $key => $value) {
            $key = strtolower($key);
            $key = str_replace('_', '', $key);
            switch ($key) {
                case 'objectmanager':
                    $this->objectManager = $value;
                    break;
                case 'hydratorname':
                    $this->hydratorName = $value;
                    break;
                case 'doctrinehydrator':
                    $this->doctrineHydrator = $value;
                    break;
                default:
                    break;
            }
        }
    }

    public function getArrayCopy()
    {
        $return = parent::getArrayCopy();
        $return['object_manager'] = $this->objectManager;
        $return['hydrator_name'] = $this->hydratorName;
        $return['doctrine_hydrator'] = $this->doctrineHydrator;
        return $return;
    }
}