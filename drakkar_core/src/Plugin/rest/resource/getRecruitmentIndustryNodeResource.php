<?php

namespace Drupal\drakkar_core\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * provide resource to get basic node info.
 *
 * @RestResource(
 *   id = "get_recruitment_node_resource",
 *   label = @Translation("Get recruitment industry node resource"),
 *   uri_paths = {
 *     "canonical" = "/get/recruitment/{id}"
 *   }
 * )
 */
class getRecruitmentIndustryNodeResource extends ResourceBase {

  protected $currentUser;

  protected $logger;

  protected $coreHelper;

  protected $fields;

  /**
   * constructs getRecruitmentNodeResource object.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param array $serializer_formats
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {

    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->logger = $logger;
    $this->coreHelper = \Drupal::service('drakkar_core.helper');
    $this->fields = [];
  }


  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('drakkar_core'),
      $container->get('current_user')
    );
  }


  /**
   * @param int $id
   *
   * Schema can be found here: https://drakkar-ws.herokuapp.com
   *
   * @return $this|ResourceResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get(int $id) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // Add default cache parameters.
    $cache = CacheableMetadata::createFromRenderArray([
      '#cache' => [
        'max-age' => 600,
        'contexts' => ['url.query_args'],
      ],
    ]);

    $response = [];

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
    if (!empty($node)) {
      $fields = $this->coreHelper->getNodeFields($node, $this->fields);
      $hero = [];
      if (isset($node->field_para_reference_hero) && !$node->field_para_reference_hero->isEmpty()) {
        $heroParagraph = $node->field_para_reference_hero->referencedEntities();
        $heroParagraph = reset($heroParagraph);
        $hero = $this->coreHelper->getParagraphHero($heroParagraph);
      }

      $nodeUUID = $node->get('uuid')->value;
      
      $response[$nodeUUID] = [
        'hero' => $hero,
        'content' => $fields
      ];

      $cache->addCacheableDependency($node);
      return (new ResourceResponse($response, 200))->addCacheableDependency($cache);
    }
    else {
      $response['message'] = 'Recruitment Industry node with provided ID is not found.';
      return new ResourceResponse($response, 400);
    }
  }
}