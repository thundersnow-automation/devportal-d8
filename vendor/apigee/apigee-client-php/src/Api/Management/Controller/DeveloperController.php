<?php

/*
 * Copyright 2018 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Apigee\Edge\Api\Management\Controller;

use Apigee\Edge\Api\Management\Entity\Developer;
use Apigee\Edge\Api\Management\Entity\DeveloperInterface;
use Apigee\Edge\Api\Management\Exception\DeveloperNotFoundException;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Controller\CpsLimitEntityController;
use Apigee\Edge\Controller\CpsListingEntityControllerTrait;
use Apigee\Edge\Controller\EntityCrudOperationsControllerTrait;
use Apigee\Edge\Controller\StatusAwareEntityControllerTrait;
use Apigee\Edge\Denormalizer\AttributesPropertyDenormalizer;
use Psr\Http\Message\UriInterface;

/**
 * Class DeveloperController.
 */
class DeveloperController extends CpsLimitEntityController implements DeveloperControllerInterface
{
    use AttributesAwareEntityControllerTrait;
    use CpsListingEntityControllerTrait;
    use EntityCrudOperationsControllerTrait;
    use StatusAwareEntityControllerTrait;

    /**
     * DeveloperController constructor.
     *
     * @param string $organization
     * @param \Apigee\Edge\ClientInterface $client
     * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface[]|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface[] $entityNormalizers
     * @param OrganizationControllerInterface|null $organizationController
     */
    public function __construct(
        string $organization,
        ClientInterface $client,
        array $entityNormalizers = [],
        ?OrganizationControllerInterface $organizationController = null
    ) {
        $entityNormalizers[] = new AttributesPropertyDenormalizer();
        parent::__construct($organization, $client, $entityNormalizers, $organizationController);
    }

    /**
     * @inheritdoc
     */
    public function getDeveloperByApp(string $appName): DeveloperInterface
    {
        $uri = $this->getBaseEndpointUri()->withQuery(http_build_query(['app' => $appName]));
        $responseArray = $this->responseToArray($this->client->get($uri));
        // When developer has not found by app we are still getting back HTTP 200 with an empty developer array.
        if (empty($responseArray['developer'])) {
            throw new DeveloperNotFoundException(
                $this->client->getJournal()->getLastResponse(),
                $this->client->getJournal()->getLastRequest()
            );
        }
        $values = reset($responseArray['developer']);

        return $this->entityTransformer->denormalize($values, $this->getEntityClass());
    }

    /**
     * Returns the API endpoint that the controller communicates with.
     *
     * In case of an entity that belongs to an organisation it should return organization/[orgName]/[endpoint].
     *
     * @return UriInterface
     */
    protected function getBaseEndpointUri(): UriInterface
    {
        return $this->client->getUriFactory()
            ->createUri("/organizations/{$this->organization}/developers");
    }

    /**
     * @inheritdoc
     */
    protected function getEntityClass(): string
    {
        return Developer::class;
    }
}
