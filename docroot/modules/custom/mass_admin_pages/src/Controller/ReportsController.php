<?php

namespace Drupal\mass_admin_pages\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * System Manager Service.
 */
class ReportsController extends ControllerBase {

  /**
   * Get content from config and render.
   */
  public function build() {
    $output = [
      '#markup' => $this->t('You do not have any administrative items.'),
    ];
    $text_field = \Drupal::state()->get('mass_admin_pages.reports_author_block_settings.text_field');
    if (!empty($text_field)) {
      $output = [
        '#markup' => Markup::create($text_field),
      ];
    }

    return $output;
  }

}
