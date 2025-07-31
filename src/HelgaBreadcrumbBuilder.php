<?php

declare(strict_types=1);

namespace Drupal\helga_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\easy_breadcrumb\EasyBreadcrumbBuilder;
use Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Provides a breadcrumb generation strategy for orphaned entities.
 *
 * This service integrates with the EasyBreadcrumbBuilder and MenuBasedBreadcrumbBuilder
 * to generate breadcrumbs for entities that do not have a parent in the menu structure.
 * It determines the active trail path for such entities based on configuration settings
 * and the menu link manager.
 */
final class HelgaBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * The menu name for Helga breadcrumbs.
   *
   * @var string
   */
  private string $helgaBreadcrumbsMenu;

  /**
   * The active trail path for orphaned entities.
   *
   * This is used to determine the breadcrumb trail for entities that do not
   * have a parent in the menu structure.
   *
   * @var array<string>
   */
  private array $orphansActiveTrail = [];

  /**
   * HelgaBreadcrumbBuilder constructor.
   * 
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder $menuBreadcrumbBuilder
   *   The menu-based breadcrumb builder service.
   * @param \Drupal\easy_breadcrumb\EasyBreadcrumbBuilder $easyBreadcrumbBuilder
   *   The path-based breadcrumb builder service.
   */
  public function __construct(
    protected readonly MenuLinkManagerInterface $menuLinkManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly MenuBasedBreadcrumbBuilder $menuBreadcrumbBuilder,
    protected readonly EasyBreadcrumbBuilder $easyBreadcrumbBuilder,
    ) {
      $this->getHelgaBreadcrumbsMenu();
    }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    // If the Helga breadcrumbs menu is not set, exit as early as possible.
    if (empty($this->helgaBreadcrumbsMenu)) {
      return FALSE;
    }

    // If the EasyBreadcrumbBuilder does not apply, exit early.
    // This essentially checks if the route is an admin route and breadcrumbs
    // for admin routes are enabled.
    if (!$this->easyBreadcrumbBuilder->applies($route_match)) {      
      return FALSE;
    }

    $entity = $this->getRouteMatchEntity($route_match);
    if (!$entity instanceof EntityInterface) {
      return FALSE;
    }

    $this->getActiveTrailPathForOrphans($entity);
    if (empty($this->orphansActiveTrail)) {
      $this->orphansActiveTrail = [];
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    // Use the menu breadcrumb builder to create the breadcrumb.
    $this->menuBreadcrumbBuilder->setMenuName($this->helgaBreadcrumbsMenu);
    // @see https://www.drupal.org/project/menu_breadcrumb/issues/3538685
    // @phpstan-ignore-next-line
    $this->menuBreadcrumbBuilder->setMenuTrail($this->orphansActiveTrail);
    return $this->menuBreadcrumbBuilder->build($route_match);
  }

  /**
   * Returns the menu parent for orphaned entities.
   *
   * This method retrieves the menu link content that should be used as the
   * parent for orphaned entities. It checks if the entity type has a bundle
   * and retrieves the corresponding menu item ID from the entity bundle's
   * third-party settings.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to retrieve the menu parent.
   * @return \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent|null
   *   The menu link content that serves as the parent for orphans, or NULL if
   *   no suitable parent is found.
   */
  public function getMenuParentForOrphans(ContentEntityInterface $entity): ?MenuLinkContent {
    if (!$entity->getEntityType()->hasKey('bundle')) {
      return NULL;
    }
    // Get the entity bundle and check if it has third-party settings for
    // Helga breadcrumbs.
    $entity_bundle = $entity->get($entity->getEntityType()->getKey('bundle'))->entity;
    $helgaBreadcrumbsMenu = $this->configFactory->get('helga_breadcrumbs.settings')->get('breadcrumbs_orphans_menu');
    if (!$helgaBreadcrumbsMenu) {
      return NULL;
    }
    if (!$entity_bundle instanceof ThirdPartySettingsInterface) {
      return NULL;
    }
    $orphansMenuItemId = $entity_bundle->getThirdPartySetting('helga_breadcrumbs', 'orphans_menu_item_id', NULL);
    if (!$orphansMenuItemId) {
      return NULL;
    }
    assert(is_string($orphansMenuItemId), 'The orphans menu item ID should be a string.');
    // Check if the orphans menu item ID belongs to the Helga breadcrumbs menu.
    $menuName = strtok($orphansMenuItemId, ':');
    if ($helgaBreadcrumbsMenu != $menuName) {
      return NULL;
    }
    $menuItemId = str_replace($menuName . ':', '', $orphansMenuItemId);
    $menuLinkContent = $this->menuLinkManager->getInstance(['id' => $menuItemId]);
    assert($menuLinkContent instanceof MenuLinkContent, 'Menu link content plugin must be an instance of MenuLinkContent.');
    return $menuLinkContent;
  }

  /**
    * Helper function to extract the entity for the supplied route.
    *
    * @return null|\Drupal\Core\Entity\ContentEntityInterface
    *   The entity if found, otherwise NULL.
    *
    * @see https://www.computerminds.co.uk/drupal-code/get-entity-route
    */
  protected function getRouteMatchEntity(RouteMatchInterface $route_match): ?ContentEntityInterface {
    // Entity will be found in the route parameters.
    $routeObject = $route_match->getRouteObject();
    if (!$routeObject) {
      return NULL;
    }
    $parameters = $routeObject->getOption('parameters');
    if (!$parameters) {
      return NULL;
    }
    assert(is_array($parameters), 'The route parameters should be an array.');
    // Determine if the current route represents an entity.
    foreach ($parameters as $name => $options) {
      if (is_array($options) && isset($options['type']) &&
        is_string($options['type']) &&
        strpos($options['type'], 'entity:') === 0) {
        $entity = $route_match->getParameter($name);
        if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
          return $entity;
        }
      }
    }
    // If no entity is found, return NULL.
    return NULL;
  }

  /**
   * Gets (and sets) a fallback active trail path for orphaned entities.
   *
   * This method retrieves the active trail path for orphaned entities based on
   * the menu link content that serves as the parent for orphans. If no such
   * menu link content is found, it sets the active trail to an empty array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *  The entity for which to retrieve the active trail path.
   *
   */
  protected function getActiveTrailPathForOrphans(ContentEntityInterface $entity): void {
    $menuLinkContent = $this->getMenuParentForOrphans($entity);

    // If no menu link content is found, reset the cached trail and return.
    if (!$menuLinkContent) {
      $this->orphansActiveTrail = [];
      return;
    }

    $pluginId = $menuLinkContent->getPluginId();
    $this->orphansActiveTrail = $this->menuLinkManager->getParentIds($pluginId) + [$pluginId => $pluginId];
  }

  /**
   * Gets (and sets) the Helga breadcrumbs menu name.
   * 
   * @return string
   *   The Helga breadcrumbs menu name.
   */
  protected function getHelgaBreadcrumbsMenu(): string {
    $configValue = $this->configFactory->get('helga_breadcrumbs.settings')->get('breadcrumbs_orphans_menu') ?? '';
    assert(is_string($configValue), 'The breadcrumbs orphans menu should be a string.');
    $this->helgaBreadcrumbsMenu = $configValue;
    return $this->helgaBreadcrumbsMenu;
  }

}
