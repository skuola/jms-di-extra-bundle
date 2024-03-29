<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\DiExtraBundle\Metadata\Driver;

use JMS\DiExtraBundle\Metadata\ClassMetadata;
use Metadata\Driver\DriverInterface;

class ConfiguredControllerInjectionsDriver implements DriverInterface
{
    private $delegate;
    private $propertyInjections;
    private $methodInjections;

    public function __construct(DriverInterface $driver, array $propertyInjections, array $methodInjections)
    {
        $this->delegate = $driver;
        $this->propertyInjections = $propertyInjections;
        $this->methodInjections = $methodInjections;
    }

    public function loadMetadataForClass(\ReflectionClass $class): ?ClassMetadata
    {
        $metadata = $this->delegate->loadMetadataForClass($class);

        if (!preg_match('/Controller\\\(.+)Controller$/', $class->name)) {
            return $metadata;
        }

        if (null === $metadata) {
            $metadata = new ClassMetadata($class->name);
        }

        foreach ($metadata->reflection->getProperties() as $property) {
            // explicit injection configured?
            if (isset($metadata->properties[$property->name])) {
                continue;
            }

            // automatic injection configured?
            if (!isset($this->propertyInjections[$property->name])) {
                continue;
            }

            if ($property->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            $metadata->properties[$property->name] = $this->propertyInjections[$property->name];
        }

        foreach ($metadata->reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // explicit injection configured?
            foreach ($metadata->methodCalls as $call) {
                if ($call[0] === $method->name) {
                    continue 2;
                }
            }

            // automatic injection configured?
            if (!isset($this->methodInjections[$method->name])) {
                continue;
            }

            if ($method->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            $metadata->methodCalls[] = array($method->name, $this->methodInjections[$method->name]);
        }

        return $metadata->properties || $metadata->methodCalls || $metadata->lookupMethods ? $metadata : null;
    }
}
