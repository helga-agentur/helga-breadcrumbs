<?php

declare(strict_types=1);

namespace Drupal\helga_breadcrumbs;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service provider for the Helga Breadcrumbs module.
 *
 * @see https://www.drupal.org/node/2026959
 */
final class HelgaBreadcrumbsServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    if (!$container->hasDefinition('menu_breadcrumb.breadcrumb.default')) {
      return;
    }
    $flattenArray = function ($array) {
      $flatArray = [];
      assert(is_array($array), 'The input should be an array.');
      array_walk_recursive($array, function($value, $key) use (&$flatArray) {
          $flatArray[$key] = $value;
      });
      return $flatArray;
    };
    $tagProperties = $container->getDefinition('menu_breadcrumb.breadcrumb.default')->getTag('breadcrumb_builder');
    $tagProperties = $flattenArray(...$tagProperties);
    $priority = $tagProperties['priority'] ?? 0;
    if ($priority == 0) {
      return;
    }
    assert(is_int($priority), 'The priority should be an integer.');
    $container
      ->register('helga_breadcrumbs.breadcrumb', HelgaBreadcrumbBuilder::class)
      ->addTag('breadcrumb_builder', ['priority' => $priority - 1])
      ->addArgument(new Reference('plugin.manager.menu.link'))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('menu_breadcrumb.breadcrumb.default'))
      ->addArgument(new Reference('easy_breadcrumb.breadcrumb'));
  }

}
