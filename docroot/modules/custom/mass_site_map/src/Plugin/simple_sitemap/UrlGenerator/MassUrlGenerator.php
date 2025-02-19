<?php

namespace Drupal\mass_site_map\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;

/**
 * Class MassUrlGenerator.
 *
 * @UrlGenerator(
 *   id = "mass_entity",
 *   label = @Translation("Entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle overrides."),
 * )
 */
class MassUrlGenerator extends EntityUrlGenerator {

  /**
   * Override of the parent function, but mostly the same.
   *
   * @inheritdoc
   */
  protected function processDataSet($data_set) {
    $entities = $this->entityTypeManager->getStorage($data_set['entity_type'])->loadMultiple((array) $data_set['id']);
    if (empty($entities)) {
      return FALSE;
    }

    $paths = [];
    foreach ($entities as $entity) {
      $entity_settings = $this->generator
        ->setVariants($this->sitemapVariant)
        ->getEntityInstanceSettings($entity->getEntityTypeId(), $entity->id());

      if (empty($entity_settings['index'])) {
        continue;
      }

      // Also, respect our custom checkbox for omitting from search
      if (method_exists($entity, 'getSearch') && $entity->getSearch()->getString()) {
        continue;
      }

      $url_object = $entity->toUrl()->setAbsolute();

      // Do not include external paths.
      if (!$url_object->isRouted()) {
        continue;
      }

      // Some bundles have custom settings.
      $mass_path_value = [];
      switch ($entity->bundle()) {
        case 'document':
          $mass_path_value = $this->fixDocumentLinks($entity);
          break;

        case 'location':
          $mass_path_value = $this->fixLocationLinks($entity);
          break;

      }
      $default_path = [
        'url' => $url_object,
        'lastmod' => method_exists($entity, 'getChangedTime') ? date('c', $entity->getChangedTime()) : NULL,
        'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
        'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
        'images' => !empty($entity_settings['include_images']) ? $this->getEntityImageData($entity) : [],

        // Additional info useful in hooks.
        'meta' => [
          'path' => $url_object->getInternalPath(),
          'entity_info' => [
            'entity_type' => $entity->getEntityTypeId(),
            'id' => $entity->id(),
          ],
        ],
      ];

      $paths[] = array_replace($default_path, $mass_path_value);
    }
    return $paths;
  }

  /**
   * The Mass.gov adjustments to Locaiton entities in the sitemap.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Plain old entity object.
   */
  protected function fixDocumentLinks(EntityInterface $entity) {
    /** @var \Drupal\media\Entity\Media $entity */
    $data = [];

    /** @var \Drupal\mass_metatag\Service\MassMetatagUtilities $utility_service */
    $utility_service = \Drupal::service('mass_metatag.utilities');

    $files = $entity->get('field_upload_file')->referencedEntities();
    if ($entity->getEntityTypeId() == 'media') {
      foreach ($files as $file) {
        // We must pass URL object so that module can apply baseurl config.
        $file_url = $file->createFileUrl();
        if ($file_url) {
          $media_url = $entity->toUrl()->toString();
          $media_url_object = Url::fromUserInput($media_url . '/download', ['absolute' => TRUE]);
          $data = [
            'url' => $media_url_object,
            'lastmod' => date('c', $entity->getChangedTime()),
            'priority' => 0.5,
            'changefreq' => 'daily',
          ];
          if ($start = $entity->get('field_start_date')->date) {
            $data['pagemap']['metatags'][] = [
              'name' => 'mg_date',
              'value' => $start->format('Ymd'),
            ];
          }
          // Add document category term name if present.
          if ($category = $entity->get('field_category')) {
            if ($term = $category->entity) {
              $data['pagemap']['metatags'][] = [
                'name' => 'category',
                'value' => $term->label(),
              ];
            }
          }
          if (isset($entity->field_organizations)) {
            /** @var \Drupal\node\Entity\Node[] $org_nodes */
            $org_nodes = $entity->field_organizations->referencedEntities();
            $org_slugs = [];
            foreach ($org_nodes as $org) {
              $org_slugs += str_replace("-", "", $utility_service->getAllOrgsFromNode($org));
            }
            if (!empty($org_slugs)) {
              $data['pagemap']['metatags'][] = [
                'name' => 'mg_organization',
                'value' => implode(',', $org_slugs),
              ];
            }
          }
          // Associate a file with its corresponding Media Entity title.
          if ($field_title = $entity->get('field_title')) {
            if ($title = $field_title->getValue()) {
              $data['pagemap']['metatags'][] = [
                'name' => 'mg_title',
                'value' => $title[0]['value'],
              ];
            }
          }
        }
      }
    }

    if (isset($entity->field_document_type)) {
      /** @var \Drupal\taxonomy\Entity\Term $type_term */
      $type_terms = $entity->field_document_type->referencedEntities();
      $term_slugs = [];
      foreach ($type_terms as $term) {
        $term_slugs[] = $utility_service->slugify($term->getName());
      }
      if (!empty($term_slugs)) {
        $data['pagemap']['metatags'][] = [
          'name' => 'mg_type',
          'value' => implode(',', $term_slugs),
        ];
      }
    }

    return $data;
  }

  /**
   * The Mass.gov adjustments to Location entities in the sitemap.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Plain old entity object.
   */
  protected function fixLocationLinks(EntityInterface $entity) {
    $url_object = Url::fromRoute('view.locations.page', ['node' => $entity->id()]);
    $url_object->setOption('absolute', TRUE);

    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime') ? date('c', $entity->getChangedTime()) : NULL,
      'priority' => 0.5,
      'changefreq' => 'daily',
    ];
  }

}
